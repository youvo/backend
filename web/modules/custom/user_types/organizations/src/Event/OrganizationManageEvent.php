<?php

namespace Drupal\organizations\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\youvo\Event\TimestampedEventInterface;

/**
 * Defines an organization manage event.
 *
 * @see OrganizationManageResource::post
 */
class OrganizationManageEvent extends Event implements TimestampedEventInterface {

  /**
   * Constructs a OrganizationManageEvent object.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The organization.
   * @param \Drupal\creatives\Entity\Creative $manager
   *   The manager.
   * @param int|null $timestamp
   *   The time of the event, NULL for now.
   */
  public function __construct(
    protected Organization $organization,
    protected Creative $manager,
    protected ?int $timestamp = NULL,
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

  /**
   * Gets the time of the event, NULL for now.
   */
  public function getTimestamp(): ?int {
    return $this->timestamp;
  }

}
