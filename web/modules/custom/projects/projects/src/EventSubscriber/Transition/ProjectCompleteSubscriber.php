<?php

namespace Drupal\projects\EventSubscriber\Transition;

use Drupal\Core\Database\Connection;
use Drupal\projects\Event\ProjectCompleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project complete event.
 */
class ProjectCompleteSubscriber implements EventSubscriberInterface {

  /**
   * Constructs the subscriber.
   */
  public function __construct(protected Connection $database) {}

  /**
   * Listens to the project complete event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\lifecycle\Exception\LifecycleTransitionException
   */
  public function onProjectComplete(ProjectCompleteEvent $event): void {

    $project = $event->getProject();
    $project->lifecycle()->complete($event->getTimestamp());

    // The project is completed only together with its results.
    $transaction = $this->database->startTransaction();
    try {
      $project->save();
      $result = $project->getResult();
      $result->setFiles($event->getFiles());
      $result->setLinks($event->getLinks());
      $result->save();
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectCompleteEvent::class => ['onProjectComplete', 1000]];
  }

}
