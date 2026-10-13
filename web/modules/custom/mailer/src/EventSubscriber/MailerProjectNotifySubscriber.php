<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ProjectActionMailerInterface;
use Drupal\projects\Event\ProjectNotifyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends mail on project notify.
 */
class MailerProjectNotifySubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected ProjectActionMailerInterface $mailer) {}

  /**
   * Sends the notify mail with the link set by the event subscribers before.
   */
  public function mail(ProjectNotifyEvent $event): void {
    $this->mailer->notify($event->getProject(), $event->getLink());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectNotifyEvent::class => ['mail', -100]];
  }

}
