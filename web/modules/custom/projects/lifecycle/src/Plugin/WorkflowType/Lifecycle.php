<?php

declare(strict_types=1);

namespace Drupal\lifecycle\Plugin\WorkflowType;

use Drupal\workflows\Plugin\WorkflowTypeBase;
use Drupal\workflows\StateInterface;

/**
 * Lifecycle workflow type.
 *
 * @WorkflowType(
 *   id = "lifecycle",
 *   label = @Translation("Lifecycle"),
 *   required_states = {},
 *   forms = {
 *     "configure" = "\Drupal\lifecycle\Form\WorkflowTypeConfigureForm"
 *   },
 * )
 */
class Lifecycle extends WorkflowTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getInitialState(): StateInterface {
    return $this->getState($this->configuration['initial_state']);
  }

}
