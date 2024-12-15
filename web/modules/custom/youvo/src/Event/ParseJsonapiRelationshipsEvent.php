<?php

namespace Drupal\youvo\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event that is fired when JSON:API relationships are parsed.
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
  public function getResource(): array {
    return $this->resource;
  }

  /**
   * Sets the resource.
   */
  public function setResource(array $resource): static {
    $this->resource = $resource;
    return $this;
  }

  /**
   * Gets the keys.
   */
  public function getKeys(): array {
    return $this->keys;
  }

  /**
   * Gets the parent key.
   */
  public function getParentKey(): string {
    return $this->parentKey;
  }

}
