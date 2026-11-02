<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectDemoteEvent;
use Drupal\projects\Exception\ProjectActionException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project demote event.
 */
class ProjectDemoteSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project demote event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\projects\Exception\ProjectActionException
   */
  public function onProjectDemote(ProjectDemoteEvent $event): void {
    $project = $event->getProject();
    if ($project->isArchived()) {
      throw new ProjectActionException('Cannot demote an archived project.');
    }
    if ($project->lifecycle()->isDeclined()) {
      throw new ProjectActionException('Cannot demote a declined project.');
    }
    $project->setPromoted(FALSE);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectDemoteEvent::class => ['onProjectDemote', 1000]];
  }

}
