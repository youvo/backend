<?php

namespace Drupal\projects\Event;

use Drupal\creatives\Entity\Creative;
use Drupal\projects\ProjectInterface;

/**
 * Defines a project apply event.
 */
class ProjectApplyEvent extends ProjectEventBase {

  /**
   * The message.
   */
  protected string $message = '';

  /**
   * The phone number.
   */
  protected string $phoneNumber = '';

  /**
   * Constructs a ProjectApplyEvent object.
   */
  public function __construct(
    ProjectInterface $project,
    protected Creative $applicant,
    ?int $timestamp = NULL,
  ) {
    parent::__construct($project, $timestamp);
  }

  /**
   * Gets the applicant.
   */
  public function getApplicant(): Creative {
    return $this->applicant;
  }

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

}
