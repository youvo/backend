<?php

declare(strict_types = 1);

namespace Drupal\youvo_lifecycle\Plugin\WorkflowType;

use Drupal\workflows\Plugin\WorkflowTypeBase;

/**
 * Workflow field Workflow.
 *
 * @WorkflowType(
 *   id = "youvo_lifecycle",
 *   label = @Translation("Youvo Lifecycle"),
 *   required_states = {},
 *   forms = {
 *     "configure" = "\Drupal\youvo_lifecycle\Form\WorkflowTypeConfigureForm"
 *   },
 * )
 */
class YouvoLifecycle extends WorkflowTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getInitialState() {
    return $this->getState($this->configuration['initial_state']);
  }

}
