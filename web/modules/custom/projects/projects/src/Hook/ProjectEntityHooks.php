<?php

namespace Drupal\projects\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\projects\ProjectInterface;

/**
 * Entity hook implementations of the projects module.
 */
class ProjectEntityHooks {

  /**
   * Implements hook_ENTITY_TYPE_presave() for projects.
   *
   * Runs after the field items' preSave(), so derived values like the image
   * dimensions don't count as content changes.
   */
  #[Hook('project_presave')]
  public function projectPresave(ProjectInterface $project): void {
    $project->trackContentChanges();
  }

}
