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
   * Sends the invite mail, which carries a door to the draft.
   */
  public function mail(ProjectInviteEvent $event): void {
    $this->mailer->invite($event->getProject());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectInviteEvent::class => ['mail', -100]];
  }

}
