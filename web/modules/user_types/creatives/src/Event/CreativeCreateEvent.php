<?php

namespace Drupal\creatives\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\creatives\Entity\Creative;

/**
 * Defines a creative create event.
 */
class CreativeCreateEvent extends Event {

  /**
   * Constructs a CreativeCreateEvent object.
   *
   * @param \Drupal\creatives\Entity\Creative $creative
   *   The creative.
   */
  public function __construct(protected Creative $creative) {}

  /**
   * Gets the created creative.
   */
  public function getCreative(): Creative {
    return $this->creative;
  }

}
