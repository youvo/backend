<?php

declare(strict_types = 1);

namespace Drupal\lifecycle\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Validation constraint for the youvo lifecycle.
 *
 * @Constraint(
 *   id = "LifecycleConstraint",
 *   label = @Translation("LifecyleConstraint provider constraint", context = "Validation"),
 * )
 */
class LifecycleContraint extends Constraint {

  /**
   * Message displayed during an invalid transition.
   *
   * @var string
   */
  public string $message = 'No transition exists to move from %previous_state to %state.';

  /**
   * Message displayed to users without appropriate permission for a transition.
   *
   * @var string
   */
  public string $insufficientPermissionsTransition = 'You do not have sufficient permissions to use the %transition transition.';

}
