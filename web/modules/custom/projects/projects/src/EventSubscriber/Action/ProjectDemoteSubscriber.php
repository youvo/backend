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
