<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\projects\Event\ProjectSubmitEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends mail on project submit.
 */
class MailerProjectSubmitSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected ProjectTransitionMailerInterface $mailer) {}

  /**
   * Sends the submit mails.
   */
  public function mail(ProjectSubmitEvent $event): void {
    $this->mailer->submit($event->getProject());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectSubmitEvent::class => ['mail', -100]];
  }

}
