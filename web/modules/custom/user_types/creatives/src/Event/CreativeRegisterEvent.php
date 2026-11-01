<?php

namespace Drupal\creatives\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\creatives\Entity\Creative;
use Drupal\youvo\Event\TimestampedEventInterface;

/**
 * Defines a creative create event.
 */
class CreativeRegisterEvent extends Event implements TimestampedEventInterface {

  /**
   * Constructs a CreativeRegisterEvent object.
   *
   * @param \Drupal\creatives\Entity\Creative $creative
   *   The creative.
   * @param string $link
   *   The registration link.
   * @param int|null $timestamp
   *   The time the creative registered, NULL for now.
   */
  public function __construct(
    protected Creative $creative,
    protected string $link,
    protected ?int $timestamp = NULL,
  ) {}

  /**
   * Gets the created creative.
   */
  public function getCreative(): Creative {
    return $this->creative;
  }

  /**
   * Gets the registration link.
   */
  public function getLink(): string {
    return $this->link;
  }

  /**
   * Gets the time the creative registered, NULL for now.
   */
  public function getTimestamp(): ?int {
    return $this->timestamp;
  }

}
