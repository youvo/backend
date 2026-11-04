<?php

namespace Drupal\projects\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\projects\ProjectInterface;
use Drupal\projects\Service\ProjectChangesBuilder;

/**
 * Hook implementations for the changes card on the project page.
 */
class ProjectChangesHooks {

  public function __construct(
    protected ProjectChangesBuilder $changesBuilder,
  ) {}

  /**
   * Implements hook_entity_extra_field_info().
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $extra['project']['project']['display']['project_changes'] = [
      'label' => new TranslatableMarkup('Changes'),
      'description' => new TranslatableMarkup('The revisions with their changed fields, for staff.'),
      'weight' => 100,
      'visible' => FALSE,
    ];
    return $extra;
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for projects.
   */
  #[Hook('project_view')]
  public function projectView(array &$build, ProjectInterface $project, EntityViewDisplayInterface $display, string $view_mode): void {

    if (!$display->getComponent('project_changes')) {
      return;
    }

    $access = $project->access('view all revisions', NULL, TRUE);
    $cache = CacheableMetadata::createFromObject($access)->addCacheableDependency($project);
    $build['project_changes'] = [];
    if ($access->isAllowed()) {
      $changes = $this->changesBuilder->build($project);
      foreach ($changes['entries'] as &$entry) {
        $entry['url'] = Url::fromRoute('entity.project.revision', [
          'project' => $project->id(),
          'project_revision' => $entry['revision_id'],
        ])->toString();
        foreach ($entry['fields'] as &$field) {
          $field['diff'] = ['#markup' => $field['diff'], '#allowed_tags' => ['del', 'ins', 'p', 'br']];
        }
      }
      unset($entry, $field);
      if ($changes['entries']) {
        $build['project_changes'] = [
          '#theme' => 'project_changes',
          '#entries' => $changes['entries'],
          '#earlier' => $changes['earlier'],
          '#count' => count($changes['entries']) + $changes['earlier'],
        ];
      }
    }
    $cache->applyTo($build['project_changes']);
  }

}
