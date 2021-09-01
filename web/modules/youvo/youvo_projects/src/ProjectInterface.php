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
   * @return string
   *   String with current state of project.
   */
  public function getState();

}
