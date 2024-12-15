<?php

namespace Drupal\organizations\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;

/**
 * Defines an organization manage event.
 *
 * @see OrganizationManageResource::post
 */
class OrganizationManageEvent extends Event {

  /**
   * Constructs a OrganizationManageEvent object.
   */
  public function __construct(
    protected Organization $organization,
    protected Creative $manager,
  ) {}

  /**
   * Gets the organization.
   */
  public function getOrganization(): Organization {
    return $this->organization;
  }

  /**
   * Gets the manager.
   */
  public function getManager(): Creative {
    return $this->manager;
  }

}
