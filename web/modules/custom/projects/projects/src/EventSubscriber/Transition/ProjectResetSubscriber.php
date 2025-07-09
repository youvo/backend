<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\projects\Event\ProjectResetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project reset event.
 */
class ProjectResetSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project reset event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\lifecycle\Exception\LifecycleTransitionException
   */
  public function onProjectReset(ProjectResetEvent $event): void {
    $project = $event->getProject();
    $project->lifecycle()->reset($event->getTimestamp());
    $project->setPromoted(FALSE);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectResetEvent::class => 'onProjectReset'];
  }

}
