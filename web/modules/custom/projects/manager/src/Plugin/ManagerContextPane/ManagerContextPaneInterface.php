<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\projects\ProjectInterface;

/**
 * Defines an interface for manager context pane plugins.
 */
interface ManagerContextPaneInterface {

  /**
   * Builds the render array for the context pane.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project entity.
   *
   * @return array
   *   A render array for the context pane.
   */
  public function build(ProjectInterface $project): array;

}
