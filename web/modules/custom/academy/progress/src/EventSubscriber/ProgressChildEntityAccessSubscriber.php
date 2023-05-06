<?php

namespace Drupal\progress\EventSubscriber;

use Drupal\academy\AcademicFormatInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Access\AccessResult;
use Drupal\progress\ProgressManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for the child entities access event.
 */
class ProgressChildEntityAccessSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a ProgressChildEntityAccessSubscriber object.
   *
   * @param \Drupal\progress\ProgressManager $progressManager
   *   The progress manager.
   */
  public function __construct(protected ProgressManager $progressManager) {}

  /**
   * Checks access for child entities in context of progress.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function checkAccess(Event $event) {

    // Skip, if this is not an academy entity.
    /** @var \Drupal\child_entities\Event\ChildEntityAccessEvent $event */
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
  public static function getSubscribedEvents() {
    return ['Drupal\child_entities\Event\ChildEntityAccessEvent' => 'checkAccess'];
  }

}
