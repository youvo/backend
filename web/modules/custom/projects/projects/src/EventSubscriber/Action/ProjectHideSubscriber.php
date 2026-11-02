<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectHideEvent;
use Drupal\projects\Exception\ProjectActionException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project hide event.
 */
class ProjectHideSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project hide event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\projects\Exception\ProjectActionException
   */
  public function onProjectHide(ProjectHideEvent $event): void {
    $project = $event->getProject();
    if ($project->isArchived()) {
      throw new ProjectActionException('Cannot hide an archived project.');
    }
    $lifecycle = $project->lifecycle();
    if (!$lifecycle->isOpen() && !$lifecycle->isOngoing() && !$lifecycle->isCompleted()) {
      throw new ProjectActionException('Cannot hide a draft, pending or declined project.');
    }
    $project->setPromoted(FALSE);
    $project->setHidden();
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectHideEvent::class => ['onProjectHide', 1000]];
  }

}
