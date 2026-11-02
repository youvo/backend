<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectShowEvent;
use Drupal\projects\Exception\ProjectActionException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project show event.
 */
class ProjectShowSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project show event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\projects\Exception\ProjectActionException
   */
  public function onProjectShow(ProjectShowEvent $event): void {
    $project = $event->getProject();
    if ($project->isArchived()) {
      throw new ProjectActionException('Cannot show an archived project.');
    }
    $lifecycle = $project->lifecycle();
    if (!$lifecycle->isOpen() && !$lifecycle->isOngoing() && !$lifecycle->isCompleted()) {
      throw new ProjectActionException('Cannot show a draft, pending or declined project.');
    }
    $project->setHidden(FALSE);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectShowEvent::class => ['onProjectShow', 1000]];
  }

}
