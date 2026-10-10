<?php

namespace Drupal\mailer\EventSubscriber;

use Drupal\creatives\Event\CreativeRegisterEvent;
use Drupal\mailer\Mailer\YouvoUserMailerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends mail on creative registration.
 */
class MailerCreativeRegisterSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected YouvoUserMailerInterface $mailer) {}

  /**
   * Sends the registration mail with the link of the event.
   */
  public function mail(CreativeRegisterEvent $event): void {
    $this->mailer->registerCreative($event->getCreative(), $event->getLink());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [CreativeRegisterEvent::class => 'mail'];
  }

}
