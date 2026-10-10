<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\projects\Event\ProjectCompleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends mail on project complete.
 */
class MailerProjectCompleteSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected ProjectTransitionMailerInterface $mailer) {}

  /**
   * Sends the complete mails.
   */
  public function mail(ProjectCompleteEvent $event): void {
    $this->mailer->complete($event->getProject());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectCompleteEvent::class => ['mail', -100]];
  }

}
