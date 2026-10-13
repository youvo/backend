<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\YouvoUserMailerInterface;
use Drupal\organizations\Event\OrganizationCreateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Notifies the team when an organization is created.
 */
class MailerOrganizationCreateSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected YouvoUserMailerInterface $mailer) {}

  /**
   * Sends the notification mail to the site mail address.
   */
  public function mail(OrganizationCreateEvent $event): void {
    $this->mailer->notifyOrganizationCreated($event->getOrganization());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [OrganizationCreateEvent::class => ['mail', -100]];
  }

}
