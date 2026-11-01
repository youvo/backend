<?php

namespace Drupal\projects\Event;

use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectDeclineReason;

/**
 * Defines a project decline event.
 *
 * Carries the rejection mail: an edited subject and body replace the default
 * template of the reason.
 */
class ProjectDeclineEvent extends ProjectEventBase {

  /**
   * The reason for declining the project.
   */
  protected ProjectDeclineReason $reason = ProjectDeclineReason::Custom;

  /**
   * The edited subject of the mail, or NULL for the default template.
   */
  protected ?string $subject = NULL;

  /**
   * The edited plain text body of the mail, or NULL for the default template.
   */
  protected ?string $body = NULL;

  /**
   * The account that declines the project and sends the mail.
   */
  protected ?AccountInterface $sender = NULL;

  /**
   * Gets the reason.
   */
  public function getReason(): ProjectDeclineReason {
    return $this->reason;
  }

  /**
   * Sets the reason.
   */
  public function setReason(ProjectDeclineReason $reason): static {
    $this->reason = $reason;
    return $this;
  }

  /**
   * Sets the edited mail.
   */
  public function setMail(string $subject, string $body): static {
    $this->subject = $subject;
    $this->body = $body;
    return $this;
  }

  /**
   * Gets the edited subject, or NULL for the default template.
   */
  public function getSubject(): ?string {
    return $this->subject;
  }

  /**
   * Gets the edited body, or NULL for the default template.
   */
  public function getBody(): ?string {
    return $this->body;
  }

  /**
   * Sets the account that declines the project.
   */
  public function setSender(AccountInterface $sender): static {
    $this->sender = $sender;
    return $this;
  }

  /**
   * Gets the account that declines the project.
   */
  public function getSender(): ?AccountInterface {
    return $this->sender;
  }

}
