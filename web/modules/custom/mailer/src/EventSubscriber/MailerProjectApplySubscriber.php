<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ProjectActionMailerInterface;
use Drupal\projects\Event\ProjectApplyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends mail on project apply.
 */
class MailerProjectApplySubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected ProjectActionMailerInterface $mailer) {}

  /**
   * Sends the apply mails.
   */
  public function mail(ProjectApplyEvent $event): void {
    $this->mailer->apply($event->getProject(), $event->getApplicant(), $event->getMessage(), $event->getPhoneNumber());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectApplyEvent::class => 'mail'];
  }

}
