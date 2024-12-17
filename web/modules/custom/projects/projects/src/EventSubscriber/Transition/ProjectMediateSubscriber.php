<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\lifecycle\Exception\LifecycleTransitionException;
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

    // Check whether there are any selected creatives.
    if (empty($selected_creatives)) {
      throw new LifecycleTransitionException('Unable to mediate project without selecting creatives.');
    }

    // Get project applicants and check if selected creatives are applicable.
    $applicants = $project->getApplicants();
    if (count(array_intersect($selected_creatives, $applicants)) !== count($selected_creatives)) {
      throw new LifecycleTransitionException('Some selected creatives did not apply for this project.');
    }

    // Transition project.
    $project->lifecycle()->mediate();
    $project->setParticipants($selected_creatives);
    $project->setPromoted(FALSE);
    if ($manager = $project->getOwner()->getManager()) {
      $project->appendParticipant($manager, 'Manager');
    }
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectMediateEvent::class => 'onProjectMediate'];
  }

}
