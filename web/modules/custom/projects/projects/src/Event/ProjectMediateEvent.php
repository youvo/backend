<?php

namespace Drupal\projects\Event;

/**
 * Defines a project mediate event.
 */
class ProjectMediateEvent extends ProjectEventBase {

  /**
   * An array of creatives.
   */
  protected array $creatives = [];

  /**
   * Gets the creatives.
   */
  public function getCreatives(): array {
    return $this->creatives;
  }

  /**
   * Sets the acreatives.
   */
  public function setCreatives(array $creatives): static {
    $this->creatives = $creatives;
    return $this;
  }

}
