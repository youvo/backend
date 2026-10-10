<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\projects\Event\ProjectMediateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends mail on project mediate.
 */
class MailerProjectMediateSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected ProjectTransitionMailerInterface $mailer) {}

  /**
   * Sends the mediate mails.
   */
  public function mail(ProjectMediateEvent $event): void {
    $this->mailer->mediate($event->getProject(), $event->getCreatives());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectMediateEvent::class => 'mail'];
  }

}
