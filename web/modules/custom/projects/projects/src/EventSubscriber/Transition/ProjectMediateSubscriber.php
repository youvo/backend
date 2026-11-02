<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\user_types\Utility\Profile;
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

    // The manager participates as manager, also if selected as creative.
    $manager = $project->getOwner()->getManager();
    $manager_id = $manager ? Profile::id($manager) : NULL;
    $selected_creatives = array_filter($event->getCreatives(), static fn($creative) => Profile::id($creative) !== $manager_id);
    $project->setParticipants($selected_creatives);
    if ($manager) {
      $project->appendParticipant($manager, 'Manager');
    }

    // The participants are set first, because the transition checks them.
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
