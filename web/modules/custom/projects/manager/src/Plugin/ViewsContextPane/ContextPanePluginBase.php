<?php

namespace Drupal\manager\Plugin\ViewsContextPane;

use Drupal\Component\Plugin\PluginBase;
use Drupal\projects\Entity\Project;

/**
 *
 */
abstract class ContextPanePluginBase extends PluginBase {

  /**
   * Returns the render array for the context pane.
   *
   * @param mixed $project
   *   The project entity or object.
   *
   * @return array
   *   A render array for the context pane.
   */
  abstract public function build(Project $project): array;

}
