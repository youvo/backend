<?php

namespace Drupal\youvo_projects\Entity;

use Drupal\node\Entity\Node;
use Drupal\youvo_projects\ProjectInterface;

/**
 *
 */
class Project extends Node implements ProjectInterface {

  const STATE_DRAFT = 'draft';
  const STATE_PENDING = 'pending';
  const STATE_OPEN = 'open';
  const STATE_ONGOING = 'ongoing';
  const STATE_COMPLETED = 'completed';

  /**
   * Gets current state of project.
   */
  public function getState() {
    return $this->get('field_lifecycle')->value;
  }

  /**
   * Checks if project can transition to state 'ongoing'.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function canMediate() {
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->loadWorkflowForProject();
    $current_state = $this->getState();
    return $current_state != self::STATE_ONGOING &&
      $workflow->getTypePlugin()->hasTransitionFromStateToState($current_state, self::STATE_ONGOING);
  }

  /**
   * Loads workflow for current project.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function loadWorkflowForProject() {
    return \Drupal::entityTypeManager()->getStorage('workflow')->load('project_lifecycle');
  }

}
