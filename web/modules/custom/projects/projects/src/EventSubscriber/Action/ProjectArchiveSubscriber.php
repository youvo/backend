<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectArchiveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project archive event.
 */
class ProjectArchiveSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project archive event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onProjectArchive(ProjectArchiveEvent $event): void {
    $project = $event->getProject();
    if ($project->isArchived()) {
      throw new \LogicException('The project is already archived.');
    }
    $project->setPromoted(FALSE);
    $project->setArchived();
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectArchiveEvent::class => ['onProjectArchive', 1000]];
  }

}
