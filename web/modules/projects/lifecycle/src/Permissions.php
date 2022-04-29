<?php

declare(strict_types = 1);

namespace Drupal\lifecycle;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\TransitionInterface;

/**
 * Defines a class for dynamic permissions based on transitions.
 *
 * @internal
 */
class Permissions {

  use StringTranslationTrait;

  /**
   * Returns an array of permissions.
   *
   * @return array
   *   The dynamic permissions.
   */
  public function getPermissions(): array {
    $permissions = [];
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    foreach (Workflow::loadMultipleByType('lifecycle') as $workflow) {
      foreach ($workflow->getTypePlugin()->getTransitions() as $transition) {
        $permissions['use ' . $workflow->id() . ' transition ' . $transition->id()] = [
          'title' => $this->t('%workflow workflow: Use %transition transition.', [
            '%workflow' => $workflow->label(),
            '%transition' => $transition->label(),
          ]),
        ];
      }
    }
    return $permissions;
  }

  /**
   * Determines whether a user can use a transition.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user accounts.
   * @param string $workflowId
   *   The workflow the transition belongs to.
   * @param \Drupal\workflows\TransitionInterface|string $transition
   *   The transition or the transition ID.
   *
   * @return bool
   *   Whether the user can use the transition.
   */
  public static function useTransition(AccountInterface $account, string $workflowId, TransitionInterface|string $transition): bool {
    $transition_id = $transition instanceof TransitionInterface ? $transition->id() : $transition;
    return $account->hasPermission(sprintf('use %s transition %s', $workflowId, $transition_id));
  }

}
