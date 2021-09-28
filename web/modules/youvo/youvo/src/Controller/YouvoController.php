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

    $organisation_ids = \Drupal::entityQuery('user')
      ->condition('roles', 'organisation')
      ->execute();

    $wurst = youvo_dummy_get_random_organisation($organisation_ids);

    if ($project instanceof Project) {
      dvp($project->getState());
    }

    return [
      '#page' => $page,
    ];
  }

}
