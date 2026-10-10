<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\projects\Event\ProjectPublishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends mail on project publish.
 */
class MailerProjectPublishSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected ProjectTransitionMailerInterface $mailer) {}

  /**
   * Sends the publish mail.
   */
  public function mail(ProjectPublishEvent $event): void {
    $this->mailer->publish($event->getProject());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectPublishEvent::class => 'mail'];
  }

}
