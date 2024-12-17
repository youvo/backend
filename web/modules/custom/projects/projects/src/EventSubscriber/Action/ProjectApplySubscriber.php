<?php

namespace Drupal\projects\EventSubscriber\Action;

use Drupal\projects\Event\ProjectApplyEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Defines event subscriber for the project apply event.
 */
class ProjectApplySubscriber implements EventSubscriberInterface {

  /**
   * Listens to the project apply event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onProjectApply(ProjectApplyEvent $event): void {

    $applicant = $event->getApplicant();
    if ($phone_number = $event->getPhoneNumber()) {
      $applicant->setPhoneNumber($phone_number);
      $applicant->save();
    }

    $project = $event->getProject();
    $project->appendApplicant($applicant);
    $project->save();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ProjectApplyEvent::class => 'onProjectApply'];
  }

}
