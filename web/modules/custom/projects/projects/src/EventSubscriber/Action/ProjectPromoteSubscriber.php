<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectPromoteEvent;
use Drupal\projects\Exception\ProjectActionException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project promote event.
 */
class ProjectPromoteSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project promote event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\projects\Exception\ProjectActionException
   */
  public function onProjectPromote(ProjectPromoteEvent $event): void {
    $project = $event->getProject();
    if ($project->isArchived()) {
      throw new ProjectActionException('Cannot promote an archived project.');
    }
    if ($project->isHidden() || $project->lifecycle()->isDeclined()) {
      throw new ProjectActionException('Cannot promote a hidden or declined project.');
    }
    $project->setPromoted(TRUE);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectPromoteEvent::class => ['onProjectPromote', 1000]];
  }

}
