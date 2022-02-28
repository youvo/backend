<?php

namespace Drupal\proposals;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a proposal entity type.
 */
interface ProposalInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the proposal creation timestamp.
   *
   * @return int
   *   Creation timestamp of the proposal.
   */
  public function getCreatedTime();

  /**
   * Sets the proposal creation timestamp.
   *
   * @param int $timestamp
   *   The proposal creation timestamp.
   *
   * @return \Drupal\courses\CourseInterface
   *   The called proposal entity.
   */
  public function setCreatedTime(int $timestamp);

}
