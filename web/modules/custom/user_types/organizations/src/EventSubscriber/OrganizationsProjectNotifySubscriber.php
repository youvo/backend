<?php

namespace Drupal\organizations\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Config\ConfigFactoryInterface;
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
    protected ConfigFactoryInterface $config,
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

    if ($organization->hasRoleProspect()) {
      // @todo Adjust langcode.
      // $organization->getPreferredLangcode();
      $langcode = 'de';
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
      $event->setLink($invitation_link);
    }
    else {
      $path = '/draft/' . $event->getProject()->uuid();
      if ($this->config->get('oauth_grant.settings')->get('local')) {
        $redirect_link = Url::fromUri('http://localhost:3000' . $path)->toString();
      }
      else {
        $redirect_link = Url::fromUri('https://hub.dev.youvo.org' . $path)->toString();
      }
      $event->setLink($redirect_link);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['Drupal\projects\Event\ProjectNotifyEvent' => ['process', 100]];
  }

}
