<?php

namespace Drupal\youvo\Event;

/**
 * Defines an interface for events that provide a timestamp.
 */
interface TimestampedEventInterface {

  /**
   * Gets the timestamp of the event.
   *
   * Usually this is only used for creating fake content. Rely on the request
   * time otherwise.
   */
  public function getTimestamp(): ?int;

}
