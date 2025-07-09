<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\projects\Event\ProjectMediateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project mediate event.
 */
class ProjectMediateSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project mediate event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\lifecycle\Exception\LifecycleTransitionException
   */
  public function onProjectMediate(ProjectMediateEvent $event): void {

    $project = $event->getProject();
    $selected_creatives = $event->getCreatives();
    $project->setParticipants($selected_creatives);

    if ($manager = $project->getOwner()->getManager()) {
      $project->appendParticipant($manager, 'Manager');
    }

    $project->lifecycle()->mediate($event->getTimestamp());
    $project->setPromoted(FALSE);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectMediateEvent::class => ['onProjectMediate', 1000]];
  }

}
