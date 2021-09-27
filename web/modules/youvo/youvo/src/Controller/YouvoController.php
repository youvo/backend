<?php

namespace Drupal\youvo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\project\Entity\Project;

/**
 * Controller for youvo_work landing pages.
 */
class YouvoController extends ControllerBase {

  /**
   * Simple Dashboard.
   */
  public function dashboard() {

    $page = [];

    $project = $this->entityTypeManager()->getStorage('node')->load(1);

    if ($project instanceof Project) {
      dvp($project->getState());
    }

    return [
      '#page' => $page,
    ];
  }

}
