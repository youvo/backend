<?php

namespace Drupal\mailer\Mailer;

use Drupal\Core\Session\AccountInterface;
use Drupal\mailer\Manager\ManagerMailPreview;
use Drupal\projects\ProjectInterface;
use Drupal\symfony_mailer\Component\ComponentMailerInterface;

/**
 * Mails a manager sends from the project manager.
 */
interface ManagerMailerInterface extends ComponentMailerInterface {

  /**
   * Renders a preview through the real mail pipeline without sending.
   *
   * Returns NULL when the mail is not offered for the project's state or the
   * project has no recipient address. Never sends or records anything.
   */
  public function preview(ProjectInterface $project, string $subtype): ?ManagerMailPreview;

  /**
   * Sends the manager-edited plain text mail, one mail per recipient.
   *
   * Returns TRUE only if every mail was sent. Nothing is sent or recorded when
   * the mail is not offered for the project's current state.
   */
  public function send(ProjectInterface $project, string $subtype, string $subject, string $bodyText, AccountInterface $sender): bool;

}
