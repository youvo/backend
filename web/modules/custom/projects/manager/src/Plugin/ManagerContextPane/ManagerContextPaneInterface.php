<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\projects\Entity\Project;

/**
 * Defines an interface for manager context pane plugins.
 */
interface ManagerContextPaneInterface {

  /**
   * Builds the render array for the context pane.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project entity.
   *
   * @return array
   *   A render array for the context pane.
   */
  public function build(Project $project): array;

}
