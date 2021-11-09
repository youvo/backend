<?php

namespace Drupal\progress;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\courses\Entity\Course;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\Entity\Progress;
use Psr\Log\LoggerInterface;

/**
 * Service that provides functionality to manage the progress of a lecture.
 *
 * @see progress -> progress.services.yml
 */
class ProgressManager {

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
   * Logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a QuestionSubmissionResource object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, LoggerInterface $logger, Connection $database) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->logger = $logger;
    $this->database = $database;
  }

  /**
   * Returns current user ID.
   */
  public function getCurrentUserId(): int {
    return $this->currentUser->id();
  }

  /**
   * Returns the request time.
   */
  public function getRequestTime(): int {
    return $this->time->getRequestTime();
  }

  /**
   * Determines whether a lecture or course is completed.
   */
  public function getCompletedStatus(Lecture|Course $entity): bool {
    return (bool) $this->getProgressField($entity, 'completed');
  }

  /**
   * Determines unlocked status.
   */
  public function getUnlockedStatus(Lecture|Course $entity): bool {

    if ($entity instanceof Lecture) {
      return $this->getLectureUnlockedStatus($entity);
    }

    if ($entity instanceof Course) {
      return $this->getCourseUnlockedStatus($entity);
    }

    return FALSE;
  }

  /**
   * Determines whether a course is unlocked.
   */
  protected function getCourseUnlockedStatus(Course $course): bool {

    return FALSE;
  }

  /**
   * Determines whether a lecture is unlocked.
   */
  protected function getLectureUnlockedStatus(Lecture $lecture): bool {

    // Get all lecture IDs in this course.
    $course = $lecture->getParentEntity();
    $lecture_references = $course->get('lectures')->getValue();
    $lecture_ids = array_column($lecture_references, 'target_id');

    // This condition should never trigger.
    if (empty($lecture_ids)) {
      return FALSE;
    }

    // Get id and completed with custom query sorted by weight.
    // Might be faster than loading every lecture individually.
    $query = $this->database->select('lectures_field_data', 'x')
      ->condition('x.id', $lecture_ids, 'IN')
      ->orderBy('x.weight');
    $query->addField('x', 'id');
    $query->leftJoin('lecture_progress', 'p',
      '[p].[lecture] = [x].[id] AND [p].[uid] = :user', [
        ':user' => $this->currentUser->id(),
      ]);
    $query->addField('p', 'completed');
    $lectures = $query->execute()->fetchAll();

    // The first lecture is always unlocked.
    if ($lectures[0]->id == $lecture->id()) {
      return TRUE;
    }

    // Unlock if all previous lectures are completed. We can assume that the
    // current lecture ID is contained within the queried lectures.
    $i = 0;
    while ($lectures[$i]->id != $lecture->id()) {
      if (!$lectures[$i]->completed) {
        return FALSE;
      }
      $i++;
    }

    return TRUE;
  }

  /**
   * Gets a field of the progress.
   */
  protected function getProgressField(Lecture|Course $entity, string $field_name): mixed {

    $progress = NULL;

    try {
      $progress = $this->getProgress($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('Can not retrieve progress entity. %type: @message in %function (line %line of %file).', $variables);
    }
    catch (EntityMalformedException $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('The progress of the requested entity has inconsistent persistent data. %type: @message in %function (line %line of %file).', $variables);
    }

    return $progress?->get($field_name)->value;
  }

  /**
   * Gets the respective progress of the lecture or course by the current user.
   *
   * @returns \Drupal\progress\Entity\Progress|null
   *   The respective progress or NULL if no storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function getProgress(Lecture|Course $entity): ?Progress {

    $progress_entity_type_id = $entity->getEntityTypeId() . '_progress';

    // Get referenced LectureProgress.
    $query = $this->entityTypeManager->getStorage($progress_entity_type_id)
      ->getQuery();
    $progress_id = $query
      ->condition($entity->getEntityTypeId(), $entity->id())
      ->condition('uid', $this->currentUser->id())
      ->execute();

    // Return nothing if there is no progress.
    if (empty($progress_id)) {
      return NULL;
    }

    // Something went wrong here.
    if (count($progress_id) > 1) {
      throw new EntityMalformedException(
        $progress_entity_type_id,
        sprintf('The "%s" entity type query has inconsistent persistent data.', $progress_entity_type_id)
      );
    }

    // Return loaded progress.
    /** @var \Drupal\progress\Entity\Progress $progress */
    $progress = $this->entityTypeManager->getStorage($progress_entity_type_id)
      ->load(reset($progress_id));
    return $progress;
  }

}
