<?php

namespace Drupal\progress;

use Drupal\academy\AcademicFormatInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\courses\Entity\Course;
use Drupal\lectures\Entity\Lecture;
use Drupal\questionnaire\SubmissionManager;
use Psr\Log\LoggerInterface;

/**
 * Service that provides functionality to manage the progress of a lecture.
 *
 * @see progress -> progress.services.yml
 */
class ProgressManager {

  /**
   * Stores progress results.
   *
   * @var array
   */
  private $progressCache;

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
   * The submission manager.
   *
   * @var \Drupal\questionnaire\SubmissionManager
   */
  protected $submissionManager;

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
   * @param \Drupal\questionnaire\SubmissionManager $submission_manager
   *   The submission manager service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, LoggerInterface $logger, Connection $database, SubmissionManager $submission_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->logger = $logger;
    $this->database = $database;
    $this->submissionManager = $submission_manager;
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
  public function isCompleted(AcademicFormatInterface $entity): bool {

    // Look for cached results.
    $cache_result = $this->getProgressCache($entity, 'completed');
    if (isset($cache_result)) {
      return $cache_result;
    }

    // Compute result and set cache.
    $result = (bool) $this->getProgressField($entity, 'completed');
    $this->setProgressCache($entity, 'completed', $result);
    return $result;
  }

  /**
   * Determines unlocked status.
   */
  public function isUnlocked(AcademicFormatInterface $entity, AccountInterface $account = NULL): bool {

    // Look for cached results.
    $cache_result = $this->getProgressCache($entity, 'unlocked');
    if (isset($cache_result)) {
      return $cache_result;
    }

    // Compute result and set cache.
    if ($entity instanceof Lecture) {
      $result = $this->isLectureUnlocked($entity, $account);
    }
    if ($entity instanceof Course) {
      $result = $this->isCourseUnlocked($entity, $account);
    }
    if (isset($result)) {
      $this->setProgressCache($entity, 'unlocked', $result);
      return $result;
    }

    // Fallback.
    return FALSE;
  }

  /**
   * Determines whether a course is unlocked.
   *
   * @todo Rework unlocking mechanism.
   */
  protected function isCourseUnlocked(Course $course, ?AccountInterface $account): bool {

    // Get all course IDs present.
    $courses = $this->getCoursesByCompleted($account);

    // The first course is always unlocked.
    // The first lecture is always unlocked.
    if ($courses[0]->id == $course->id()) {
      return TRUE;
    }

    // Unlock if all previous courses are completed. We can assume that the
    // current course ID is contained within the queried courses.
    $i = 0;
    while ($courses[$i]->id != $course->id()) {
      if (!$courses[$i]->completed) {
        return FALSE;
      }
      $i++;
    }

    return TRUE;
  }

  /**
   * Determines whether a lecture is unlocked.
   */
  protected function isLectureUnlocked(Lecture $lecture, ?AccountInterface $account): bool {

    // Get all lecture IDs in this course.
    $course = $lecture->getParentEntity();
    $lectures = $this->getReferencedLecturesByCompleted($course, $account);

    // This condition should never trigger.
    if (empty($lectures)) {
      return FALSE;
    }

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
   * Returns progress in percent for course.
   */
  public function calculateProgression(Course $course): int {

    // If the course is completed the progress is full.
    if ($this->isCompleted($course)) {
      return 100;
    }

    // If the course is not unlocked there is no progress.
    // Note that the edge case of a course being completed but not unlocked is
    // covered by the previous conditional.
    if (!$this->isUnlocked($course)) {
      return 0;
    }

    // Otherwise, calculate the percentage of completed lectures in the course.
    $lectures = $this->getReferencedLecturesByCompleted($course);

    // If there are no lectures, there is no progress.
    if (empty($lectures)) {
      return 0;
    }

    $total = count($lectures);
    $completed = count(array_filter($lectures, fn($l) => $l->completed));
    $progression = ceil($completed / $total * 100);

    // Here, we cover an edge case. An example scenario:
    // The user has completed 4/5 lectures. The 5th lecture gets deleted by
    // the editor. Then the progression is 100, but the course is not
    // completed. Therefore, we grant the course completion on access, when
    // progression is 100.
    if ($progression == 100) {
      try {
        $progress = $this->loadProgress($course);
        $progress->setCompletedTime($this->getRequestTime());
        $progress->save();
        $this->setProgressCache($course, 'completed', TRUE);
      }
      catch (EntityStorageException | InvalidPluginDefinitionException | PluginNotFoundException $e) {
        $variables = Error::decodeException($e);
        $this->logger
          ->error('Progression 100, but not completed. Can not retrieve progress or save entity. %type: @message in %function (line %line of %file).', $variables);
      }
      catch (EntityMalformedException $e) {
        $variables = Error::decodeException($e);
        $this->logger
          ->error('Progression 100, but not completed. The progress of the requested entity has inconsistent persistent data. %type: @message in %function (line %line of %file).', $variables);
      }
    }

    return $progression;
  }

  /**
   * Returns the current unlocked lecture.
   *
   * Selects the lecture that is currently unlocked.
   * Otherwise, if present, return the first lecture of the course.
   */
  public function currentLecture(Course $course): ?Lecture {

    // Retrieve lectures from course.
    $lectures = $course->getLectures();

    // If there are no lectures, return nothing.
    if (empty($lectures)) {
      return NULL;
    }

    // The first lecture is the fallback value.
    $first = $lectures[0];

    // Return first lecture, if the is course completed or not unlocked.
    if ($this->isCompleted($course) || !$this->isUnlocked($course)) {
      return $first;
    }

    // Otherwise, return first lecture that is not completed.
    $lectures_by_completed = $this->getReferencedLecturesByCompleted($course);

    // This condition should never trigger.
    if (empty($lectures_by_completed)) {
      return NULL;
    }

    // Get the first lecture that is not completed.
    $lecture = current(array_filter($lectures_by_completed,
      fn($l) => !$l->completed));

    // If somehow all lectures are completed return the first lecture.
    if (!$lecture) {
      return $first;
    }

    // Get selected lecture from references and check unlocked status again.
    $lecture = array_filter($lectures, fn ($l) => $l->id() == $lecture->id);
    $lecture = reset($lecture);
    return $this->isUnlocked($lecture) ? $lecture : $first;
  }

  /**
   * Determines if given lecture is last lecture of course.
   *
   * @returns bool
   *    The decision if last lecture.
   */
  public function isLastLecture(Lecture $lecture): bool {
    $lectures = $this->getReferencedLecturesByCompleted($lecture->getParentEntity());
    return !empty($lectures) &&
      $lectures[array_key_last($lectures)]->id == $lecture->id();
  }

  /**
   * Determines if all required questions were answered.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function requiredQuestionsAnswered(Lecture $lecture) {

    $answered = TRUE;

    if ($questionnaires = array_filter($lecture->getParagraphs(),
      fn($p) => $p->bundle() == 'questionnaire')) {
      /** @var \Drupal\questionnaire\Entity\Questionnaire[] $questionnaires */
      /** @var \Drupal\questionnaire\Entity\Question[] $questions */
      foreach ($questionnaires as $questionnaire) {
        $questions = $questionnaire->getQuestions();
        foreach ($questions as $question) {
          if ($question->isRequired()) {
            $answered = $this->submissionManager->isAnswered($question);
          }
        }
      }
    }

    return $answered;
  }

  /**
   * Gets the respective progress of the lecture or course by the current user.
   *
   * @returns \Drupal\progress\ProgressInterface|null
   *   The respective progress or NULL if no storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function loadProgress(AcademicFormatInterface $entity, AccountInterface $account = NULL): ?ProgressInterface {

    $progress_entity_type_id = $entity->getEntityTypeId() . '_progress';

    // Get uid.
    $uid = isset($account) ? $account->id() : $this->currentUser->id();

    // Get referenced Progress.
    $query = $this->entityTypeManager->getStorage($progress_entity_type_id)
      ->getQuery();
    $progress_id = $query
      ->condition($entity->getEntityTypeId(), $entity->id())
      ->condition('uid', $uid)
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
    /** @var \Drupal\progress\ProgressInterface $progress */
    $progress = $this->entityTypeManager->getStorage($progress_entity_type_id)
      ->load(reset($progress_id));
    return $progress;
  }

  /**
   * Gets a field of the progress.
   */
  protected function getProgressField(AcademicFormatInterface $entity, string $field_name, AccountInterface $account = NULL): mixed {

    $progress = NULL;

    try {
      $progress = $this->loadProgress($entity, $account);
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
   * Get referenced lectures with completed status.
   */
  private function getReferencedLecturesByCompleted(Course $course, AccountInterface $account = NULL): array {

    $lecture_references = $course->get('lectures')->getValue();
    $lecture_ids = array_column($lecture_references, 'target_id');

    // This condition should never trigger.
    if (empty($lecture_ids)) {
      return [];
    }

    // Get id and completed with custom query sorted by weight.
    // Might be faster than loading every lecture individually.
    $query = $this->database->select('lectures_field_data', 'x')
      ->condition('x.id', $lecture_ids, 'IN')
      ->orderBy('x.weight');
    $query->addField('x', 'id');
    $query->leftJoin('lecture_progress', 'p',
      '[p].[lecture] = [x].[id] AND [p].[uid] = :user', [
        ':user' => isset($account) ? $account->id() : $this->currentUser->id(),
      ]);
    $query->addField('p', 'completed');
    return $query->execute()->fetchAll();
  }

  /**
   * Get referenced lectures with completed status.
   */
  private function getCoursesByCompleted(AccountInterface $account = NULL) {

    // Get id and completed with custom query sorted by weight.
    // Might be faster than loading every course individually.
    $query = $this->database->select('course_field_data', 'x')
      ->orderBy('x.weight');
    $query->addField('x', 'id');
    $query->leftJoin('course_progress', 'p',
      '[p].[course] = [x].[id] AND [p].[uid] = :user', [
        ':user' => isset($account) ? $account->id() : $this->currentUser->id(),
      ]);
    $query->addField('p', 'completed');
    return $query->execute()->fetchAll();
  }

  /**
   * Get cache for previously calculated results.
   */
  private function getProgressCache(AcademicFormatInterface $entity, string $request): ?bool {
    if (!in_array($request, ['unlocked', 'completed'])) {
      return NULL;
    }
    if (isset($this->progressCache[$entity->getEntityTypeId()][$entity->id()][$request])) {
      return $this->progressCache[$entity->getEntityTypeId()][$entity->id()][$request];
    }
    return NULL;
  }

  /**
   * Set cache for calculated results.
   */
  private function setProgressCache(AcademicFormatInterface $entity, string $request, bool $value) {
    if (!in_array($request, ['unlocked', 'completed'])) {
      return;
    }
    $this->progressCache[$entity->getEntityTypeId()][$entity->id()][$request] = $value;
  }

}
