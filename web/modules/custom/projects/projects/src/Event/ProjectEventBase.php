<?php

namespace Drupal\projects\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\projects\ProjectInterface;
use Drupal\youvo\Event\TimestampedEventInterface;

/**
 * Defines a base class for project events.
 */
abstract class ProjectEventBase extends Event implements TimestampedEventInterface {

  /**
   * Constructs a ProjectEventBase object.
   */
  public function __construct(
    protected ProjectInterface $project,
    protected ?int $timestamp = NULL,
  ) {}

  /**
   * Gets the project.
   */
  public function getProject(): ProjectInterface {
    return $this->project;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp(): ?int {
    return $this->timestamp;
  }

}
