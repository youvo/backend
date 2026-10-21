<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\projects\Event\ProjectDeclineEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project decline event.
 */
class ProjectDeclineSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project decline event.
   *
   * The project remains published. Access to declined projects is governed by
   * the lifecycle state, so that a reset restores a fully usable draft.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\lifecycle\Exception\LifecycleTransitionException
   */
  public function onProjectDecline(ProjectDeclineEvent $event): void {
    $project = $event->getProject();
    $project->lifecycle()->decline($event->getTimestamp());
    $project->setPromoted(FALSE);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectDeclineEvent::class => ['onProjectDecline', 1000]];
  }

}
