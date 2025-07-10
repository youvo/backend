<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectShowEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project show event.
 */
class ProjectShowSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project show event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onProjectShow(ProjectShowEvent $event): void {
    $project = $event->getProject();
    if ($project->lifecycle()->isDraft() || $project->lifecycle()->isPending()) {
      throw new \LogicException('Cannot show a project in a draft or pending state.');
    }
    $project->setPublished();
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectShowEvent::class => ['onProjectShow', 1000]];
  }

}
