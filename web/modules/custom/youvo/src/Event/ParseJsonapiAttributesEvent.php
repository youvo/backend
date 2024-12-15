<?php

namespace Drupal\youvo\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when JSON:API relationships are parsed.
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
   */
  public function __construct(array $item) {
    $this->item = $item;
  }

  /**
   * Gets the item.
   */
  public function getItem(): array {
    return $this->item;
  }

  /**
   * Sets the item.
   */
  public function setItem(array $item): static {
    $this->item = $item;
    return $this;
  }

}
