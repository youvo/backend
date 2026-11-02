<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectShowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project show event.
 */
class ProjectShowSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project show event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onProjectShow(ProjectShowEvent $event): void {
    $project = $event->getProject();
    if ($project->isArchived()) {
      throw new \LogicException('Cannot show an archived project.');
    }
    // Like the resource: only open, ongoing or completed projects are shown.
    if (!$project->lifecycle()->isOpen() && !$project->lifecycle()->isOngoing() && !$project->lifecycle()->isCompleted()) {
      throw new \LogicException('Cannot show a project in a draft, pending or declined state.');
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
