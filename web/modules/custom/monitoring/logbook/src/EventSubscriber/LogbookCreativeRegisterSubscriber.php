<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\creatives\Event\CreativeRegisterEvent;

/**
 * Logbook creative register event subscriber.
 */
class LogbookCreativeRegisterSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = CreativeRegisterEvent::class;
  const LOG_PATTERN = 'creative_register';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog()) {
      return;
    }
    /** @var \Drupal\creatives\Event\CreativeRegisterEvent $event */
    $log->setCreatives([$event->getCreative()]);
    $log->save();
  }

}
