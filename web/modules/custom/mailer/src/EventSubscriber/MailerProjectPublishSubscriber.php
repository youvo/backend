<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mailer\Entity\TransactionalEmail;
use Drupal\projects\Event\ProjectPublishEvent;

/**
 * Mailer project publish event subscriber.
 *
 * @todo Placeholder implementation. Needs to be reviewed in project lifecycle.
 */
class MailerProjectPublishSubscriber extends MailerSubscriberBase {

  use StringTranslationTrait;

  const EMAIL_ID = 'project_publish_organization';

  /**
   * Sends mail during project publish event.
   */
  public function mail(Event $event): void {

    $email = $this->loadTransactionalEmail(self::EMAIL_ID);
    if (!$email instanceof TransactionalEmail) {
      return;
    }

    /** @var \Drupal\projects\Event\ProjectPublishEvent $event */
    $project = $event->getProject();
    $organization = $project->getOwner();
    $manager = $organization->getManager();
    $replacements = [
      '%Contact' => $organization->getContact(),
      '%TitleProject' => $project->getTitle(),
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
    return [ProjectPublishEvent::class => 'mail'];
  }

}
