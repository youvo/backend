<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectUnarchiveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project unarchive event.
 */
class ProjectUnarchiveSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project unarchive event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onProjectUnarchive(ProjectUnarchiveEvent $event): void {
    $project = $event->getProject();
    if (!$project->isArchived()) {
      throw new \LogicException('The project is not archived.');
    }
    $project->setArchived(FALSE);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectUnarchiveEvent::class => ['onProjectUnarchive', 1000]];
  }

}
