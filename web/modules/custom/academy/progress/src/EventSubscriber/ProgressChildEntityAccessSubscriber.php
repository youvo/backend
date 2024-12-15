<?php

namespace Drupal\progress\EventSubscriber;

use Drupal\academy\AcademicFormatInterface;
use Drupal\child_entities\Event\ChildEntityAccessEvent;
use Drupal\Core\Access\AccessResult;
use Drupal\progress\ProgressManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for the child entities access event.
 */
class ProgressChildEntityAccessSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a ProgressChildEntityAccessSubscriber object.
   */
  public function __construct(protected ProgressManager $progressManager) {}

  /**
   * Checks access for child entities in context of progress.
   */
  public function checkAccess(ChildEntityAccessEvent $event): void {

    // Skip, if this is not an academy entity.
    $origin = $event->getEntity()->getOriginEntity();
    if (!$origin instanceof AcademicFormatInterface) {
      return;
    }

    // Skip, if this is an editor.
    if ($event->getAccount()->hasPermission('manage courses')) {
      return;
    }

    // Check enrollment status. Therefore, from an access point of view, all
    // contents of the course are revealed once a user enrolls into a course.
    if ($event->getAccessResult()->isAllowed()) {
      $event->setAccessResult(
        AccessResult::allowedIf(
          $this->progressManager->isUnlocked($origin, $event->getAccount())
        )
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ChildEntityAccessEvent::class => 'checkAccess'];
  }

}
