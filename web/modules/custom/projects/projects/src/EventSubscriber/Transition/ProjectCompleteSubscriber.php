<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\projects\Event\ProjectCompleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project complete event.
 */
class ProjectCompleteSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project complete event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\lifecycle\Exception\LifecycleTransitionException
   */
  public function onProjectComplete(ProjectCompleteEvent $event): void {

    $project = $event->getProject();
    $project->lifecycle()->complete();
    $project->save();

    $result = $project->getResult();
    $result->setFiles($event->getFiles());
    $result->setLinks($event->getLinks());
    $result->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectCompleteEvent::class => 'onProjectComplete'];
  }

}
