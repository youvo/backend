<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mailer\Entity\TransactionalEmail;

/**
 * Mailer project notify event subscriber.
 */
class MailerProjectNotifySubscriber extends MailerSubscriberBase {

  use StringTranslationTrait;

  const EMAIL_ID = 'project_notify';

  /**
   * Sends mail during project notify event.
   */
  public function mail(Event $event): void {

    $email = $this->loadTransactionalEmail(self::EMAIL_ID);
    if (!$email instanceof TransactionalEmail) {
      return;
    }

    /** @var \Drupal\projects\Event\ProjectNotifyEvent $event */
    /** @var \Drupal\organizations\Entity\Organization $organization */
    $organization = $event->getProject()->getOwner();
    /** @var \Drupal\creatives\Entity\Creative|null $manager */
    $manager = $organization->getManager();
    $replacements = [
      '%Contact' => $organization->getContact(),
      '%InvitationLink' => $event->getInvitationLink(),
      '%Manager' => isset($manager) ? $manager->getName() : $this->t('Dein youvo-Team'),
    ];

    $body = $this->handleTokens(
      $email->body(),
      $replacements,
      $email->tokens(TRUE)
    );

    $this->logger->info('Send %subject to %receiver: %body', [
      '%receiver' => $event->getProject()->getOwner()->getEmail(),
      '%subject' => $email->subject(),
      '%body' => $body,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['Drupal\projects\Event\ProjectNotifyEvent' => 'mail'];
  }

}
