<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\mailer\Entity\TransactionalEmail;

/**
 * Mailer project notify event subscriber.
 */
class MailerProjectNotifySubscriber extends MailerSubscriberBase {

  const TRANSACTIONAL_EMAIL_ID = 'project_notify';

  /**
   * Sends mail during project notify event.
   */
  public function mail(Event $event) {

    $transactional_email = $this->loadTransactionalEmail(self::TRANSACTIONAL_EMAIL_ID);
    if (!$transactional_email instanceof TransactionalEmail) {
      return;
    }

    /** @var \Drupal\projects\Event\ProjectNotifyEvent $event */
    $replacements = [
      '%ProjectTitle' => $event->getProject()->getTitle(),
    ];

    $body = $this->handleTokens(
      $transactional_email->body(),
      $replacements,
      $transactional_email->tokens(TRUE)
    );

    $this->logger->info('Send %subject to %receiver: %body', [
      '%receiver' => $event->getProject()->getOwner()->getEmail(),
      '%subject' => $transactional_email->subject(),
      '%body' => $body,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return ['Drupal\projects\Event\ProjectNotifyEvent' => 'mail'];
  }

}
