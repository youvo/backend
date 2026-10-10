<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\projects\Event\ProjectArchiveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project archive event.
 */
class ProjectArchiveSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project archive event.
   *
   * The project remains published. Access to archived projects is governed by
   * the lifecycle state, so that a reset restores a fully usable draft.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\lifecycle\Exception\LifecycleTransitionException
   */
  public function onProjectArchive(ProjectArchiveEvent $event): void {
    $project = $event->getProject();
    $project->lifecycle()->archive($event->getTimestamp());
    $project->setPromoted(FALSE);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectArchiveEvent::class => ['onProjectArchive', 1000]];
  }

}
