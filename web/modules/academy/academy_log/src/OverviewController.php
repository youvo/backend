<?php

namespace Drupal\academy_log;

use Drupal\Core\Controller\ControllerBase;
use Drupal\progress\ProgressManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Overview for academy log.
 */
class OverviewController extends ControllerBase {

  /**
   * The progress manager service.
   *
   * @var \Drupal\progress\ProgressManager
   */
  private ProgressManager $progressManager;

  /**
   * Construct overview controller with services.
   *
   * @param \Drupal\progress\ProgressManager $progress_manager
   *   The progress manager service.
   */
  public function __construct(ProgressManager $progress_manager) {
    $this->progressManager = $progress_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('progress.manager'),
    );
  }

  /**
   * Simple overview of academy participants.
   */
  public function overview() {

    // Initialize.
    $page = [];

    // Get all creatives, that are active and not associates.
    $associates_ids = [1, 14, 130, 136, 616, 621, 1200, 1888, 1889, 5124, 15970];
    $storage = $this->entityTypeManager()->getStorage('user');
    $uids = $storage->getQuery()
      ->condition('status', '1')
      ->condition('roles', 'creative')
      ->condition('uid', $associates_ids, 'NOT IN')
      ->execute();
    /** @var \Drupal\user\Entity\User[] $accounts */
    $accounts = $storage->loadMultiple($uids);

    foreach ($accounts as $account) {
      $page['participants'][$account->id()] = $account->get('fullname')->value;
    }

    return [
      '#theme' => 'overview',
      '#page' => $page,
    ];
  }

}
