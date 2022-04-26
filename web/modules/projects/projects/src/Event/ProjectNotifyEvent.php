<?php

namespace Drupal\projects\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a project notify event.
 */
class ProjectNotifyEvent extends Event {

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
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function __construct(ProjectInterface $project, Request $request) {
    $this->project = $project;
    $this->request = $request;
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
