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
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\Entity\LectureProgress;
use Psr\Log\LoggerInterface;

/**
 * Service that provides functionality to manage the progress of a lecture.
 *
 * @see progress -> progress.services.yml
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
   * Determines whether a lecture is completed.
   */
  public function getCompletedStatus($lecture): bool {
    return (bool) $this->getProgressField($lecture, 'completed');
  }

  /**
   * Determines whether a lecture is unlocked.
   */
  public function getUnlockedStatus(Lecture $lecture): bool {

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
  protected function getProgressField(Lecture $lecture, string $field_name): mixed {

    $progress = NULL;

    try {
      $progress = $this->getLectureProgress($lecture);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('Can not retrieve lecture_progress entity. %type: @message in %function (line %line of %file).', $variables);
    }
    catch (EntityMalformedException $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('The progress of the requested lecture has inconsistent persistent data. %type: @message in %function (line %line of %file).', $variables);
    }

    return $progress?->get($field_name)->value;
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
  public function getLectureProgress($lecture): ?LectureProgress {

    $entity_type_id = 'lecture_progress';

    // Get referenced LectureProgress.
    $query = $this->entityTypeManager->getStorage($entity_type_id)
      ->getQuery();
    $progress_id = $query
      ->condition($lecture->getEntityTypeId(), $lecture->id())
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

}
