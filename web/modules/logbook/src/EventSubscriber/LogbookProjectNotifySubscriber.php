<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\logbook\LogEventInterface;

/**
 * Logbook project notify event subscriber.
 */
class LogbookProjectNotifySubscriber extends LogbookSubscriberBase {

  use StringTranslationTrait;

  const EVENT_TYPE = 'project_notify';

  /**
   * Writes log during event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function log(Event $event): void {
    $log = $this->createLogEvent();
    if (!$log instanceof LogEventInterface) {
      return;
    }
    /** @var \Drupal\projects\Event\ProjectNotifyEvent $event */
    $log->setProject($event->getProject());
    $log->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['Drupal\projects\Event\ProjectNotifyEvent' => 'log'];
  }

}
