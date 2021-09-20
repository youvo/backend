<?php

namespace Drupal\academy\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Academy routes.
 */
class AcademyController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
