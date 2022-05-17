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

  const EMAIL_ID_PROSPECT = 'project_notify_prospect';
  const EMAIL_ID_ORGANIZATION = 'project_notify_organization';

  /**
   * Sends mail during project notify event.
   */
  public function mail(Event $event): void {

    /** @var \Drupal\projects\Event\ProjectNotifyEvent $event */
    /** @var \Drupal\organizations\Entity\Organization $organization */
    $organization = $event->getProject()->getOwner();

    $email = $this->loadTransactionalEmail(
      $organization->hasRoleProspect() ?
        self::EMAIL_ID_PROSPECT :
        self::EMAIL_ID_ORGANIZATION
    );
    if (!$email instanceof TransactionalEmail) {
      return;
    }

    /** @var \Drupal\creatives\Entity\Creative|null $manager */
    $manager = $organization->getManager();
    $replacements = [
      '%Contact' => $organization->getContact(),
      '%Link' => $event->getLink(),
      '%Manager' => isset($manager) ? $manager->getName() : $this->t('Dein youvo-Team'),
    ];

    $this->sendMail(
      $event->getProject()->getOwner()->getEmail(),
      $this->handleTokensSubject($email, $replacements),
      $this->handleTokensBody($email, $replacements),
      $manager?->getEmail()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['Drupal\projects\Event\ProjectNotifyEvent' => 'mail'];
  }

}
