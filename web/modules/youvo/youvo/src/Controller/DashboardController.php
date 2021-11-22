<?php

namespace Drupal\youvo\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for youvo_work landing pages.
 */
class DashboardController extends ControllerBase {

  /**
   * Simple Dashboard.
   */
  public function dashboard() {

    $page = [];

    // Get current username and append to page variable.
    try {
      /** @var \Drupal\user\Entity\User $current_user */
      $current_user = $this->entityTypeManager()
        ->getStorage('user')
        ->load($this->currentUser()->id());
      $page['fullname'] = $current_user->get('fullname')->value;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $this->loggerFactory->get('youvo')
        ->error('Could not load user on dashboard.');
      $page['fullname'] = $this->t('User');
    }

    // Get URL to academy administration.
    $page['academy_url'] = 'Wurst';

    return [
      '#theme' => 'dashboard',
      '#page' => $page,
    ];
  }

}
