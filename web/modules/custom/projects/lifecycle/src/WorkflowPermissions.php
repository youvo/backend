<?php

declare(strict_types=1);

namespace Drupal\lifecycle;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workflows\Entity\Workflow;
use Drupal\workflows\TransitionInterface;

/**
 * Defines a class for dynamic permissions based on transitions.
 *
 * @internal
 */
class WorkflowPermissions {

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
      $permissions['bypass ' . $workflow->id() . ' transition access'] = [
        'title' => $this->t('%workflow workflow: Bypass transition access.', [
          '%workflow' => $workflow->label(),
        ]),
      ];
    }
    return $permissions;
  }

  /**
   * Determines permission for a workflow transition.
   *
   * @param string $workflow_id
   *   The workflow the transition belongs to.
   * @param \Drupal\workflows\TransitionInterface|string $transition
   *   The transition or the transition ID.
   *
   * @return string
   *   The matching workflow permission.
   */
  public static function useTransition(string $workflow_id, TransitionInterface|string $transition): string {
    $transition_id = $transition instanceof TransitionInterface ? $transition->id() : $transition;
    return sprintf('use %s transition %s', $workflow_id, $transition_id);
  }

  /**
   * Determines permission to bypass a workflow transition.
   *
   * @param string $workflow_id
   *   The workflow the transition belongs to.
   *
   * @return string
   *   The matching workflow permission.
   */
  public static function bypassTransition(string $workflow_id): string {
    return sprintf('bypass %s transition access', $workflow_id);
  }

}
