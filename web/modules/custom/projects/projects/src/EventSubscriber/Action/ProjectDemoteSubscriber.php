<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectDemoteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project demote event.
 */
class ProjectDemoteSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project demote event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onProjectDemote(ProjectDemoteEvent $event): void {
    $project = $event->getProject();
    if ($project->lifecycle()->isDeclined()) {
      throw new \LogicException('Cannot demote a declined project.');
    }
    if ($project->isArchived()) {
      throw new \LogicException('Cannot demote an archived project.');
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
