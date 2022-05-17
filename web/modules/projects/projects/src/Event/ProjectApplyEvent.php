<?php

namespace Drupal\projects\Event;

use Drupal\creatives\Entity\Creative;

/**
 * Defines a project apply event.
 */
class ProjectApplyEvent extends ProjectEventBase {

  /**
   * The applicant.
   *
   * @var \Drupal\creatives\Entity\Creative|null
   */
  protected ?Creative $applicant = NULL;

  /**
   * The message.
   *
   * @var string
   */
  protected string $message;

  /**
   * Gets the message.
   */
  public function getMessage(): string {
    return $this->message ?? '';
  }

  /**
   * Sets the message.
   */
  public function setMessage(string $message): ProjectApplyEvent {
    $this->message = $message;
    return $this;
  }

  /**
   * Gets the applicant.
   */
  public function getApplicant(): ?Creative {
    return $this->applicant;
  }

  /**
   * Sets the applicant.
   */
  public function setApplicant(Creative $applicant): ProjectApplyEvent {
    $this->applicant = $applicant;
    return $this;
  }

}
