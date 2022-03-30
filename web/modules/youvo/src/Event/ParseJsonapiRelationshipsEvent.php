<?php

namespace Drupal\youvo\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event that is fired when json api relationships are parsed.
 */
class ParseJsonapiRelationshipsEvent extends Event {

  const EVENT_NAME = 'parse_jsonapi_relationships_event';

  protected array $resource;
  protected string $parentKey;
  protected array $keys;

  /**
   * Constructs the object.
   *
   * @param array $resource
   * @param array $keys
   * @param string $parent_key
   */
  public function __construct(array $resource, array $keys, string $parent_key) {
    $this->resource = $resource;
    $this->keys = $keys;
    $this->parentKey = $parent_key;
  }

  public function getResource() {
    return $this->resource;
  }

  public function setResource(array $resource) {
    $this->resource = $resource;
    return $this;
  }

  public function getKeys() {
    return $this->keys;
  }

  public function getParentKey() {
    return $this->parentKey;
  }

}
