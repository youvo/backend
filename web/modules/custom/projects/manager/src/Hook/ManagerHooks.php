<?php

namespace Drupal\manager\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Site\Settings;
use Drupal\manager\ManagerContextPanes;
use Drupal\manager\ManagerRules;
use Drupal\manager\Plugin\ManagerRule\RuleSeverity;
use Drupal\manager\ProjectSeenTracker;
use Drupal\manager\Timeline\TimelineBuilder;
use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;
use Drupal\views\ViewExecutable;

/**
 * Hook implementations for the manager module.
 */
class ManagerHooks {

  public function __construct(
    protected ManagerContextPanes $managerContextPanes,
    protected ManagerRules $managerRules,
    protected AdminContext $adminContext,
    protected ProjectSeenTracker $projectSeenTracker,
  ) {}

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   *
   * The pane forms keep their buttons below the fields: Gin must not move them
   * into a sticky bar (and add a hidden link to the tab order).
   *
   * @return string[]
   *   The ids of the forms.
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return [
      'manager_project_note_form',
      'manager_project_mail_form',
    ];
  }

  /**
   * Implements hook_page_attachments().
   *
   * Adds the dark mode switch to the admin toolbar while developing: on DDEV,
   * or where $settings['manager_dev_tools'] is TRUE. Never in production.
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments): void {
    $enabled = Settings::get('manager_dev_tools', getenv('IS_DDEV_PROJECT') === 'true');
    if ($enabled && $this->adminContext->isAdminRoute()) {
      $attachments['#attached']['library'][] = 'manager/dev_dark_mode';
    }
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {

    $hooks = [
      'context_pane' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessContextPane',
      ],
      'manager_pane_messages' => [
        'variables' => ['messages' => []],
        'template' => 'manager-pane-messages',
      ],
      'manager_timeline' => [
        'variables' => ['rows' => [], 'add_form' => NULL],
        'template' => 'manager-timeline',
      ],
      'manager_project_events' => [
        'variables' => ['events' => [], 'total' => 0],
        'template' => 'manager-project-events',
      ],
      'manager_projects_block' => [
        'variables' => ['cards' => [], 'more_url' => NULL, 'seen_all_url' => NULL],
        'template' => 'manager-projects-block',
      ],
      'manager_rule' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessManagerRule',
      ],
    ];

    foreach ($this->managerContextPanes->getDefinitions() as $definition) {
      $hooks['context_pane__' . $definition['id']] = [
        'base hook' => 'context_pane',
      ];
    }

    return $hooks;
  }

  /**
   * Invalidates the cached timeline of the project of a log or an entry.
   *
   * The timeline of a project lists its logs and correspondence entries. Both
   * are added and removed without the project being saved.
   */
  #[Hook('log_insert')]
  #[Hook('log_update')]
  #[Hook('log_delete')]
  #[Hook('correspondence_insert')]
  #[Hook('correspondence_update')]
  #[Hook('correspondence_delete')]
  public function invalidateTimeline(ContentEntityInterface $entity): void {
    $project_id = $entity->get('project')->target_id ?? NULL;
    if ($project_id !== NULL) {
      Cache::invalidateTags([TimelineBuilder::cacheTag($project_id)]);
    }
  }

  /**
   * Prepares variables for context pane templates.
   */
  public function preprocessContextPane(array &$variables): void {
    $variables['attributes']['id'] = uniqid('context-pane--', FALSE);
    $variables['type'] = $variables['elements']['#type'] ?? NULL;
    $variables['project'] = $variables['elements']['#project'] ?? NULL;
    $variables['content'] = $variables['elements']['content'] ?? [];
  }

  /**
   * Prepares variables for manager rule templates.
   */
  public function preprocessManagerRule(array &$variables): void {
    $variables['attributes']['id'] = uniqid('manager-rule--', FALSE);
    $variables['rule'] = $variables['elements']['#rule'] ?? NULL;
    $variables['project'] = $variables['elements']['#project'] ?? NULL;
    $variables['content'] = $variables['elements']['content'] ?? [];
  }

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    if ($view->id() === 'project_manager') {
      $view->element['#attached']['library'][] = 'manager/core';
    }
  }

  /**
   * Implements hook_preprocess_views_view_table().
   */
  #[Hook('preprocess_views_view_table')]
  public function preprocessViewsViewTable(array &$variables): void {
    if ($variables['view']->id() !== 'project_manager') {
      return;
    }
    $result = $variables['view']->result;
    foreach ($variables['rows'] as $key => &$row) {

      $project = $result[$key]->_entity;

      if ($project instanceof ProjectInterface) {

        $rules = $this->managerRules->getRules($project);
        $status = &$row['columns']['field_lifecycle'];
        foreach (RuleSeverity::cases() as $severity) {
          if (array_filter($rules, static fn($r) => $r->severity() === $severity)) {
            $status['attributes']->addClass('status-' . lcfirst($severity->name));
            break;
          }
        }

        if ($project->lifecycle()->isCompleted() || $project->lifecycle()->isDeclined()) {
          $action_transition = &$row['columns']['nothing_2'];
          $action_transition['attributes']->offsetUnset('class');
          unset($action_transition['content']);
        }

        // Promote and demote are not available for archived projects.
        if ($project->lifecycle()->isDeclined()) {
          $action_edit = &$row['columns']['nothing_3'];
          $action_edit['attributes']->offsetUnset('class');
          unset($action_edit['content']);
        }
      }

    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK_alter().
   */
  #[Hook('theme_suggestions_context_pane_alter')]
  public function themeSuggestionsContextPaneAlter(array &$suggestions, array $variables): void {
    if (!empty($variables['elements']['#type'])) {
      $suggestions[] = 'context_pane__' . $variables['elements']['#type'];
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for projects.
   *
   * The confirmations of a deleted project are not needed anymore.
   */
  #[Hook('project_delete')]
  public function projectDelete(ProjectInterface $project): void {
    $this->projectSeenTracker->deleteForProject($project->id());
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for users.
   */
  #[Hook('user_delete')]
  public function userDelete(UserInterface $account): void {
    $this->projectSeenTracker->deleteForUser($account->id());
  }

}
