<?php

namespace Drupal\youvo_projects\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for transition forms.
 */
class TransitionController extends ControllerBase {

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   * @param int|null $nid
   *   The node id.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access results.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function accessProjectMediate(AccountInterface $account, int $nid = NULL) {

    /** @var \Drupal\youvo_projects\Entity\Project $project */
    $project = $this->entityTypeManager()->getStorage('node')->load($nid);

    return AccessResult::allowedIf($account->hasPermission('use project_lifecycle transition project_mediate') && $project->canMediate());
  }

}
