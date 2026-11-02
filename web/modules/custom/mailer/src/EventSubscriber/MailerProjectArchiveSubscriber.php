<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\projects\Event\ProjectArchiveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends the archive mail on project archive.
 *
 * Runs after the project was archived and saved, and only sends the mail the
 * manager edited, for open, ongoing and completed projects.
 */
class MailerProjectArchiveSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    protected ManagerMailerInterface $mailer,
    protected ManagerMailRegistry $registry,
  ) {}

  /**
   * Sends the archive mail.
   */
  public function mail(ProjectArchiveEvent $event): void {
    $project = $event->getProject();
    $subject = $event->getSubject();
    $body = $event->getBody();
    $sender = $event->getSender();
    if (!$project->isArchived() || $subject === NULL || $body === NULL || $sender === NULL) {
      return;
    }
    if (!in_array($this->registry->stateOf($project), ManagerMailRegistry::ARCHIVE_MAIL_STATES, TRUE)) {
      return;
    }
    $this->mailer->send($project, ManagerMailRegistry::archiveSubtype($event->getReason()), $subject, $body, $sender);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectArchiveEvent::class => ['mail', -100]];
  }

}
