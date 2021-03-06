<?php

namespace Drupal\organizations\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\organizations\Entity\Organization;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines an organization create event.
 */
class OrganizationCreateEvent extends Event {

  /**
   * The created organization.
   *
   * @var \Drupal\organizations\Entity\Organization
   */
  protected Organization $organization;

  /**
   * The created project ID.
   *
   * @var int
   *
   * @see \Drupal\projects\EventSubscriber\ProjectOrganizationCreateSubscriber
   */
  protected int $projectId;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Constructs a OrganizationCreateEvent object.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The created organization.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function __construct(Organization $organization, Request $request) {
    $this->organization = $organization;
    $this->request = $request;
  }

  /**
   * Gets the created organization.
   */
  public function getOrganization() {
    return $this->organization;
  }

  /**
   * Gets the request.
   */
  public function getRequest() {
    return $this->request;
  }

  /**
   * Sets the project ID.
   */
  public function setProjectId(int $project_id): OrganizationCreateEvent {
    $this->projectId = $project_id;
    return $this;
  }

  /**
   * Gets the project ID.
   */
  public function getProjectId(): int {
    return $this->projectId;
  }

}
