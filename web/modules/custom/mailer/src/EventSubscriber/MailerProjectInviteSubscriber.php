<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\projects\Event\ProjectInviteEvent;

/**
 * Mailer project invite event subscriber.
 */
class MailerProjectInviteSubscriber extends MailerProjectNotifySubscriber {

  use StringTranslationTrait;

  const EMAIL_ID = 'project_invite_organization';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectInviteEvent::class => 'mail'];
  }

}
