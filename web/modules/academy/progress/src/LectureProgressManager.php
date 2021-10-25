<?php

namespace Drupal\progress;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\Entity\LectureProgress;

/**
 * Provides functionality to manage the progress of a lecture.
 */
class LectureProgressManager {

  /**
   * The respective Lecture entity.
   *
   * @var \Drupal\lectures\Entity\Lecture
   */
  protected $lecture;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a QuestionSubmissionResource object.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The respective lecture.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(Lecture $lecture, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    $this->lecture = $lecture;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * Creates an instance.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The respective lecture.
   */
  public static function create(Lecture $lecture) {
    $container = \Drupal::getContainer();
    return new static(
      $lecture,
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * Gets the respective progress of the lecture by the current user.
   *
   * @returns \Drupal\progress\Entity\LectureProgress|null
   *   The respective progress or NULL if no storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getLectureProgress(): ?LectureProgress {

    $entity_type_id = 'lecture_progress';

    // Get referenced LectureProgress.
    $query = $this->entityTypeManager->getStorage($entity_type_id)
      ->getQuery();
    $progress_id = $query
      ->condition($this->lecture->getEntityTypeId(), $this->lecture->id())
      ->condition('uid', $this->currentUser->id())
      ->execute();

    // Return nothing if there is no progress.
    if (empty($progress_id)) {
      return NULL;
    }

    // Something went wrong here.
    if (count($progress_id) > 1) {
      throw new EntityMalformedException(
        $entity_type_id,
        sprintf('The "%s" entity type query has inconsistent persistent data.', $entity_type_id)
      );
    }

    // Return loaded progress.
    /** @var \Drupal\progress\Entity\LectureProgress $progress */
    $progress = $this->entityTypeManager->getStorage($entity_type_id)
      ->load(reset($progress_id));
    return $progress;
  }

  /**
   * Returns current user ID.
   */
  public function getCurrentUserId() {
    return $this->currentUser->id();
  }

  /**
   * Returns the request time.
   */
  public function getRequestTime() {
    return $this->time->getRequestTime();
  }

}
