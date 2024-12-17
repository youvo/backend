<?php

namespace Drupal\projects\Event;

use Drupal\creatives\Entity\Creative;

/**
 * Defines a project apply event.
 */
class ProjectApplyEvent extends ProjectEventBase {

  /**
   * The applicant.
   */
  protected ?Creative $applicant = NULL;

  /**
   * The message.
   */
  protected string $message = '';

  /**
   * The phone number.
   */
  protected string $phoneNumber = '';

  /**
   * Gets the message.
   */
  public function getMessage(): string {
    return $this->message;
  }

  /**
   * Sets the message.
   */
  public function setMessage(string $message): static {
    $this->message = $message;
    return $this;
  }

  /**
   * Gets the phone number.
   */
  public function getPhoneNumber(): string {
    return $this->phoneNumber;
  }

  /**
   * Sets the phone number.
   */
  public function setPhoneNumber(string $phone_number): static {
    $this->phoneNumber = $phone_number;
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
  public function setApplicant(Creative $applicant): static {
    $this->applicant = $applicant;
    return $this;
  }

}
