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
   * @param \Drupal\projects\Entity\Project $project
   *   The project entity.
   *
   * @return array
   *   A render array for the context pane.
   */
  public function build(Project $project): array;

}
