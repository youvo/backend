<?php

namespace Drupal\projects\Event;

use Drupal\Core\Session\AccountInterface;

/**
 * Carries the edited mail of a decline or archive event.
 *
 * Only an edited subject and body are mailed.
 */
trait ProjectMailEventTrait {

  /**
   * The edited subject of the mail, or NULL for no mail.
   */
  protected ?string $subject = NULL;

  /**
   * The edited plain text body of the mail, or NULL for no mail.
   */
  protected ?string $body = NULL;

  /**
   * The account that sends the mail.
   */
  protected ?AccountInterface $sender = NULL;

  /**
   * Sets the edited mail.
   */
  public function setMail(string $subject, string $body): static {
    $this->subject = $subject;
    $this->body = $body;
    return $this;
  }

  /**
   * Gets the edited subject, or NULL for no mail.
   */
  public function getSubject(): ?string {
    return $this->subject;
  }

  /**
   * Gets the edited body, or NULL for no mail.
   */
  public function getBody(): ?string {
    return $this->body;
  }

  /**
   * Sets the account that sends the mail.
   */
  public function setSender(AccountInterface $sender): static {
    $this->sender = $sender;
    return $this;
  }

  /**
   * Gets the account that sends the mail.
   */
  public function getSender(): ?AccountInterface {
    return $this->sender;
  }

}
