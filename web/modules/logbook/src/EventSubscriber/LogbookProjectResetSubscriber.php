<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;

/**
 * Logbook project reset event subscriber.
 */
class LogbookProjectResetSubscriber extends LogbookSubscriberBase {

  const EVENT_CLASS = 'Drupal\projects\Event\ProjectResetEvent';
  const LOG_PATTERN = 'project_reset';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    // Reset logs related to project.
    /** @var \Drupal\projects\Event\ProjectResetEvent $event */
    try {
      $log_storage = $this->entityTypeManager->getStorage('log');
      $log_ids = $log_storage->getQuery()
        ->condition('project', $event->getProject()->id())
        ->execute();
      $logs = $log_storage->loadMultiple($log_ids);
      /** @var \Drupal\logbook\LogInterface $log */
      foreach ($logs as $log) {
        $log->setUnpublished();
        $log->save();
      }
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $this->logger->error('Unable to reset logs for project %id', ['%id' => $event->getProject()->id()]);
    }

    // Log reset of project.
    if (!$log = $this->createLog()) {
      return;
    }
    $log->setProject($event->getProject());
    if ($manager = $event->getProject()->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->save();
  }

}
