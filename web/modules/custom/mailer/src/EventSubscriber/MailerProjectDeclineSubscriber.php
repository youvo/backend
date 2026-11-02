<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\projects\Event\ProjectDeclineEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends the rejection mail on project decline.
 *
 * Runs after the project was declined and saved, and only sends the mail the
 * manager edited. Like the other transition mails, a failure is logged and
 * recorded in the correspondence.
 */
class MailerProjectDeclineSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected ManagerMailerInterface $mailer) {}

  /**
   * Sends the rejection mail.
   */
  public function mail(ProjectDeclineEvent $event): void {
    $project = $event->getProject();
    $subject = $event->getSubject();
    $body = $event->getBody();
    $sender = $event->getSender();
    if (!$project->lifecycle()->isDeclined() || $subject === NULL || $body === NULL || $sender === NULL) {
      return;
    }
    $this->mailer->send($project, ManagerMailRegistry::declineSubtype($event->getReason()), $subject, $body, $sender);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectDeclineEvent::class => ['mail', -100]];
  }

}
