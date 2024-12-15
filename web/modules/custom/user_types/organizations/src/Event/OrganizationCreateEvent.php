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
   * The created project ID.
   *
   * @see \Drupal\projects\EventSubscriber\ProjectOrganizationCreateSubscriber
   */
  protected int $projectId;

  /**
   * Constructs a OrganizationCreateEvent object.
   */
  public function __construct(
    protected Organization $organization,
    protected Request $request,
  ) {}

  /**
   * Gets the created organization.
   */
  public function getOrganization(): Organization {
    return $this->organization;
  }

  /**
   * Gets the request.
   */
  public function getRequest(): Request {
    return $this->request;
  }

  /**
   * Sets the project ID.
   */
  public function setProjectId(int $project_id): static {
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
