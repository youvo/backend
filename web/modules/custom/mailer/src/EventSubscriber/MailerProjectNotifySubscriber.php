<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mailer\Entity\TransactionalEmail;
use Drupal\projects\Event\ProjectNotifyEvent;

/**
 * Mailer project notify event subscriber.
 */
class MailerProjectNotifySubscriber extends MailerSubscriberBase {

  use StringTranslationTrait;

  const EMAIL_ID = 'project_notify_organization';

  /**
   * Sends mail during project notify event.
   */
  public function mail(Event $event): void {

    $email = $this->loadTransactionalEmail(self::EMAIL_ID);
    if (!$email instanceof TransactionalEmail) {
      return;
    }

    /** @var \Drupal\projects\Event\ProjectNotifyEvent $event */
    $organization = $event->getProject()->getOwner();
    $manager = $organization->getManager();
    $replacements = [
      '%Contact' => $organization->getContact(),
      '%Link' => $event->getLink(),
      '%Manager' => isset($manager) ? $manager->getName() : $this->t('Dein youvo-Team'),
    ];

    $this->sendMail(
      $organization->getEmail(),
      $this->handleTokensSubject($email, $replacements),
      $this->handleTokensBody($email, $replacements),
      $manager?->getEmail()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectNotifyEvent::class => 'mail'];
  }

}
