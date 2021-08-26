<?php

namespace Drupal\youvo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\youvo_projects\Entity\Project;

/**
 * Controller for youvo_work landing pages.
 */
class YouvoController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function dashboard() {

    $page = [];

    $project = Project::load(1);
    dvp($project->get('title')->getValue());

    return [
      '#page' => $page,
    ];
  }

}
