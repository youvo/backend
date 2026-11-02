<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\projects\Event\ProjectUnarchiveEvent;
use Drupal\projects\ProjectInterface;

/**
 * Logbook project unarchive event subscriber.
 */
class LogbookProjectUnarchiveSubscriber extends LogbookSubscriberBase {

  const string EVENT_CLASS = ProjectUnarchiveEvent::class;
  const string LOG_PATTERN = 'project_unarchive';

  /**
   * {@inheritdoc}
   */
  public function log(Event $event): void {
    // Re-activate the logs from the latest reset on; earlier logs stay
    // archived.
    /** @var \Drupal\projects\Event\ProjectUnarchiveEvent $event */
    $project = $event->getProject();
    $this->setProjectLogsArchived($project, FALSE, $this->latestResetLogId($project));

    if (!$log = $this->createLog($event)) {
      return;
    }
    $log->setProject($project);
    $log->setOrganization($project->getOwner());
    if ($manager = $project->getOwner()->getManager()) {
      $log->setManager($manager);
    }
    $log->save();
  }

  /**
   * Gets the id of the latest reset log of the project, or 0 if none.
   */
  protected function latestResetLogId(ProjectInterface $project): int {
    try {
      $ids = $this->entityTypeManager->getStorage('log')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'project_reset')
        ->condition('project', $project->id())
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      return 0;
    }
    return (int) reset($ids);
  }

}
