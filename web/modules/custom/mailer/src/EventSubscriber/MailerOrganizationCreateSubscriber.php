<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\mailer\Mailer\YouvoUserMailerInterface;
use Drupal\organizations\Event\OrganizationCreateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends the mails when an organization proposes a project.
 */
class MailerOrganizationCreateSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected YouvoUserMailerInterface $mailer) {}

  /**
   * Notifies the staff and confirms the receipt to the organization.
   */
  public function mail(OrganizationCreateEvent $event): void {
    $organization = $event->getOrganization();
    $this->mailer->notifyOrganizationCreated($organization);
    $this->mailer->confirmOrganizationProposal($organization);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [OrganizationCreateEvent::class => ['mail', -100]];
  }

}
