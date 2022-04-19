<?php

namespace Drupal\youvo\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when json api relationships are parsed.
 */
class ParseJsonapiRelationshipsEvent extends Event {

  /**
   * The resource.
   *
   * @var array
   */
  protected array $resource;

  /**
   * The parent key of the resource.
   *
   * @var string
   */
  protected string $parentKey;

  /**
   * The keys of the resource.
   *
   * @var array
   */
  protected array $keys;

  /**
   * Constructs a ParseJsonapiRelationshipsEvent object.
   *
   * @param array $resource
   *   The resource.
   * @param array $keys
   *   The keys of the resource.
   * @param string $parent_key
   *   The parent key of the resource.
   */
  public function __construct(array $resource, array $keys, string $parent_key) {
    $this->resource = $resource;
    $this->keys = $keys;
    $this->parentKey = $parent_key;
  }

  /**
   * Gets the resource.
   */
  public function getResource() {
    return $this->resource;
  }

  /**
   * Sets the resource.
   */
  public function setResource(array $resource) {
    $this->resource = $resource;
    return $this;
  }

  /**
   * Gets the keys.
   */
  public function getKeys() {
    return $this->keys;
  }

  /**
   * Gets the parent key.
   */
  public function getParentKey() {
    return $this->parentKey;
  }

}
