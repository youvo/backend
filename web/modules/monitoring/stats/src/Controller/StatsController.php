<?php

namespace Drupal\stats\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for stats pages.
 */
class StatsController extends ControllerBase {

  /**
   * Controls overview.
   */
  public function overview() {

    $page = [];

    return [
      '#theme' => 'stats-overview',
      '#page' => $page,
    ];
  }

}
