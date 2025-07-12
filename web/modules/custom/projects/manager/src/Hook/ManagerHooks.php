<?php

namespace Drupal\manager\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\projects\ProjectInterface;
use Drupal\views\ViewExecutable;

/**
 * Hook implementations for the manager module.
 */
class ManagerHooks {

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
    ];

    /** @var \Drupal\Component\Plugin\PluginManagerInterface $context_pane_manager */
    $context_pane_manager = \Drupal::service('plugin.manager.views_context_pane');
    foreach ($context_pane_manager->getDefinitions() as $definition) {
      $hooks['context_pane__' . $definition['id']] = [
        'base hook' => 'context_pane',
      ];
    }

    return $hooks;
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
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    if ($view->id() === 'project_manager') {
      $view->element['#attached']['library'][] = 'manager/core';
    }
  }

  /**
   * Implements hook_views_post_render().
   */
  #[Hook('preprocess_views_view_table')]
  public function preprocessProjectManager(&$variables): void {
    if ($variables['view']->id() !== 'project_manager') {
      return;
    }
    $result = $variables['view']->result;
    foreach ($variables['rows'] as $key => &$row) {
      $project = $result[$key]->_entity;
      if ($project instanceof ProjectInterface && $project->lifecycle()->isCompleted()) {
        $action_transition = &$row['columns']['nothing_2'];
        $action_transition['attributes']->offsetUnset('class');
        unset($action_transition['content']);
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

}
