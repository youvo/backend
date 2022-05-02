<?php

namespace Drupal\projects\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;

/**
 * Defines a base class for project events.
 */
abstract class ProjectEventBase extends Event {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The project.
   *
   * @var \Drupal\projects\ProjectInterface
   */
  protected ProjectInterface $project;

  /**
   * Constructs a ProjectEventBase object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   */
  public function __construct(
    AccountInterface $current_user,
    ProjectInterface $project
  ) {
    $this->currentUser = $current_user;
    $this->project = $project;
  }

  /**
   * Gets the current user.
   */
  public function getCurrentUser() {
    return $this->currentUser;
  }

  /**
   * Gets the project.
   */
  public function getProject() {
    return $this->project;
  }

}
