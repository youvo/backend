<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\projects\Event\ProjectSubmitEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project submit event.
 */
class ProjectSubmitSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project submit event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\lifecycle\Exception\LifecycleTransitionException
   */
  public function onProjectSubmit(ProjectSubmitEvent $event): void {
    $project = $event->getProject();
    $project->lifecycle()->submit();
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectSubmitEvent::class => 'onProjectSubmit'];
  }

}
