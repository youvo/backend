<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Mailer project invite event subscriber.
 */
class MailerProjectInviteSubscriber extends MailerProjectNotifySubscriber {

  use StringTranslationTrait;

  const EMAIL_ID = 'project_invite';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['Drupal\projects\Event\ProjectInviteEvent' => 'mail'];
  }

}
