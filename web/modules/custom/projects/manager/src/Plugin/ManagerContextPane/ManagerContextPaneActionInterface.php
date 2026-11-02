<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\projects\ProjectInterface;

/**
 * Defines a context pane that offers the user actions on the project.
 *
 * The project list shows the trigger of such a pane only if it has actions.
 */
interface ManagerContextPaneActionInterface extends ManagerContextPaneInterface {

  /**
   * Whether the pane offers the current user an action on the project.
   *
   * Checks only the access, without building the pane content.
   */
  public function hasActions(ProjectInterface $project): bool;

}
