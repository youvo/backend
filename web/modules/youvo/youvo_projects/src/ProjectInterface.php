<?php

namespace Drupal\youvo_projects;

use Drupal\node\NodeInterface;

/**
 * Provides an interface defining a project node entity.
 */
interface ProjectInterface extends NodeInterface {

  /**
   * Method to determine current state of project.
   *
   * @return string|bool
   *   String with current cycle of project.
   *   FALSE if current cycle could not be determined.
   */
  public function getState();

}
