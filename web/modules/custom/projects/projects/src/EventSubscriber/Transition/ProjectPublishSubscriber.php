<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\projects\Event\ProjectPublishEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project publish event.
 */
class ProjectPublishSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project publish event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\lifecycle\Exception\LifecycleTransitionException
   */
  public function onProjectPublish(ProjectPublishEvent $event): void {
    $project = $event->getProject();
    $project->lifecycle()->publish($event->getTimestamp());
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectPublishEvent::class => 'onProjectPublish'];
  }

}
