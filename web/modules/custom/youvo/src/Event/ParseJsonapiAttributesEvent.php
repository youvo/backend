<?php

namespace Drupal\youvo\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when json api relationships are parsed.
 */
class ParseJsonapiAttributesEvent extends Event {

  /**
   * The item.
   *
   * @var array
   */
  protected array $item;

  /**
   * Constructs a ParseJsonapiAttributesEvent object.
   *
   * @param array $item
   *   The item.
   */
  public function __construct(array $item) {
    $this->item = $item;
  }

  /**
   * Gets the item.
   */
  public function getItem() {
    return $this->item;
  }

  /**
   * Sets the item.
   */
  public function setItem(array $item) {
    $this->item = $item;
    return $this;
  }

}
