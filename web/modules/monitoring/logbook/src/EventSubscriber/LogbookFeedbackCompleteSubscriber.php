<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;

/**
 * Logbook feedback complete event subscriber.
 */
class LogbookFeedbackCompleteSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = 'Drupal\feedback\Event\FeedbackCompleteEvent';
  const LOG_PATTERN = 'feedback_complete';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    if (!$log = $this->createLog()) {
      return;
    }
    /** @var \Drupal\feedback\Event\FeedbackCompleteEvent $event */
    $log->setProject($event->getFeedback()->getProject());
    $log->setMisc(['feedback_id' => $event->getFeedback()->id()]);
    $log->save();
  }

}
