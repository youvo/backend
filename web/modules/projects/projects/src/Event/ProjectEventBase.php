<?php

namespace Drupal\projects\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpFoundation\Request;

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
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Constructs a ProjectNotifyEvent object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function __construct(
    AccountInterface $current_user,
    ProjectInterface $project,
    Request $request
  ) {
    $this->currentUser = $current_user;
    $this->project = $project;
    $this->request = $request;
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

  /**
   * Gets the request.
   */
  public function getRequest() {
    return $this->request;
  }

}
