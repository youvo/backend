<?php

namespace Drupal\youvo_projects\Entity;

use Drupal\node\Entity\Node;
use Drupal\youvo_projects\ProjectInterface;

/**
 *
 */
class Project extends Node implements ProjectInterface {

  /**
   * Implement whatever business logic specific to basic pages.
   */
  public function getState() {
    return $this->get('field_lifecycle')->value;
  }

  /**
   * Implement whatever business logic specific to basic pages.
   */
  public function canMediate() {
    return TRUE;
  }

}
