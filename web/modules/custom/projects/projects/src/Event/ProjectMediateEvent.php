<?php

namespace Drupal\projects\Event;

/**
 * Defines a project mediate event.
 */
class ProjectMediateEvent extends ProjectEventBase {

  /**
   * An array of creatives.
   *
   * @var \Drupal\creatives\Entity\Creative[]
   */
  protected array $creatives = [];

  /**
   * Gets the creatives.
   *
   * @return \Drupal\creatives\Entity\Creative[]
   *   An array of creatives.
   */
  public function getCreatives(): array {
    return $this->creatives;
  }

  /**
   * Sets the creatives.
   *
   * @param \Drupal\creatives\Entity\Creative[] $creatives
   *   The creatives.
   *
   * @return $this
   *   The project mediate event.
   */
  public function setCreatives(array $creatives): static {
    $this->creatives = $creatives;
    return $this;
  }

}
