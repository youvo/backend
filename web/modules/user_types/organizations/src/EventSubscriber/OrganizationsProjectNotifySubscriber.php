<?php

namespace Drupal\organizations\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Organization project notify event subscriber.
 */
class OrganizationsProjectNotifySubscriber implements EventSubscriberInterface {

  /**
   * Constructs a MailerSubscriberBase object.
   */
  public function __construct(
    protected LanguageManagerInterface $languageManager,
    protected TimeInterface $time
  ) {}

  /**
   * Processes project notify event.
   */
  public function process(Event $event): void {

    /** @var \Drupal\projects\Event\ProjectNotifyEvent $event */
    $organization = $event->getProject()->getOwner();
    $timestamp = $this->time->getCurrentTime();
    $langcode = $options['langcode'] ?? $organization->getPreferredLangcode();
    $invitation_link = Url::fromRoute('organizations.invite',
      [
        'uid' => $organization->id(),
        'timestamp' => $timestamp,
        'hash' => user_pass_rehash($organization, $timestamp),
      ],
      [
        'absolute' => TRUE,
        'language' => $this->languageManager->getLanguage($langcode),
      ]
    )->toString();
    $event->setInvitationLink($invitation_link);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['Drupal\projects\Event\ProjectNotifyEvent' => ['process', 100]];
  }

}
