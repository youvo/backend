<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectHideEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project hide event.
 */
class ProjectHideSubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project hide event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onProjectHide(ProjectHideEvent $event): void {
    $project = $event->getProject();
    if ($project->lifecycle()->isDraft() || $project->lifecycle()->isPending()) {
      throw new \LogicException('Cannot hide a project in a draft or pending state.');
    }
    $project->setPromoted(FALSE);
    $project->setUnpublished();
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectHideEvent::class => ['onProjectHide', 1000]];
  }

}
