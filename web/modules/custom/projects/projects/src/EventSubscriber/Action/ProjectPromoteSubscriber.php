<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectPromoteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project promote event.
 */
class ProjectPromoteSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project promote event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onProjectPromote(ProjectPromoteEvent $event): void {
    $project = $event->getProject();
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
