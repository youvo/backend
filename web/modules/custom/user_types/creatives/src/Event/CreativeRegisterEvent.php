<?php

namespace Drupal\creatives\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\creatives\Entity\Creative;

/**
 * Defines a creative create event.
 */
class CreativeRegisterEvent extends Event {

  /**
   * Constructs a CreativeRegisterEvent object.
   *
   * @param \Drupal\creatives\Entity\Creative $creative
   *   The creative.
   * @param string $link
   *   The registration link.
   */
  public function __construct(
    protected Creative $creative,
    protected string $link,
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

}
