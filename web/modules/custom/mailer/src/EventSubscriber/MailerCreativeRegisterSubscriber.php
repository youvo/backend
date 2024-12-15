<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\creatives\Event\CreativeRegisterEvent;
use Drupal\mailer\Entity\TransactionalEmail;

/**
 * Mailer project invite event subscriber.
 */
class MailerCreativeRegisterSubscriber extends MailerSubscriberBase {

  use StringTranslationTrait;

  const EMAIL_ID = 'user_register_creative';

  /**
   * Sends mail during project notify event.
   */
  public function mail(Event $event): void {

    $email = $this->loadTransactionalEmail(self::EMAIL_ID);
    if (!$email instanceof TransactionalEmail) {
      return;
    }

    /** @var \Drupal\creatives\Event\CreativeRegisterEvent $event */
    $creative = $event->getCreative();
    $replacements = [
      '%Name' => $creative->getName(),
      '%Link' => $event->getLink(),
    ];

    $this->sendMail(
      $creative->getEmail(),
      $this->handleTokensSubject($email, $replacements),
      $this->handleTokensBody($email, $replacements)
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [CreativeRegisterEvent::class => 'mail'];
  }

}
