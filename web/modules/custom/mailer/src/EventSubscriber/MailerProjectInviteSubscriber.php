<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ProjectActionMailerInterface;
use Drupal\projects\Event\ProjectInviteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends mail on project invite.
 */
class MailerProjectInviteSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected ProjectActionMailerInterface $mailer) {}

  /**
   * Sends the invite mail with the link set by the event subscribers before.
   */
  public function mail(ProjectInviteEvent $event): void {
    $this->mailer->invite($event->getProject(), $event->getLink());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectInviteEvent::class => ['mail', -100]];
  }

}
