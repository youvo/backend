<?php

namespace Drupal\youvo\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when json api relationships are parsed.
 */
class ParseJsonapiAttributesEvent extends Event {

  const EVENT_NAME = 'parse_jsonapi_attributes_event';

  protected array $item;

  /**
   * Constructs the object.
   *
   * @param array $item
   */
  public function __construct(array $item) {
    $this->item = $item;
  }

  public function getItem() {
    return $this->item;
  }

  public function setItem(array $item) {
    $this->item = $item;
    return $this;
  }

}
