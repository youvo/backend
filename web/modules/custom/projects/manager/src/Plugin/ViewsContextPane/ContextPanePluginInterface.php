<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\projects\Entity\Project;

/**
 *
 */
interface ContextPanePluginInterface {

  /**
   * Returns the render array for the context pane.
   *
   * @param mixed $project
   *   The project entity or object.
   *
   * @return array
   *   A render array for the context pane.
   */
  public function build(Project $project): array;

}
