<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\Entity\CourseProgress;
use Drupal\progress\ProgressInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides progress lecture complete resource.
 *
 * @RestResource(
 *   id = "progress:lecture:complete",
 *   label = @Translation("Progress Lecture Complete Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/lectures/{entity}/complete"
 *   }
 * )
 */
class LectureCompleteResource extends ProgressResource {

  /**
   * Responds to POST requests.
   */
  public function post(Lecture $entity): ResourceResponseInterface {

    try {
      // Get the respective lecture progress by lecture and current user.
      $progress = $this->progressManager->loadProgress($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new UnprocessableEntityHttpException('The progress of the requested lecture has inconsistent persistent data.', $e);
    }

    // There is no progress for this lecture by this user.
    if ($progress === NULL) {
      throw new BadRequestHttpException('Creative is not enrolled in this lecture.');
    }

    // Check if all required questions are answered.
    // We are graceful, if there is an error.
    try {
      $answered = $this->progressManager->requiredQuestionsAnswered($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      $this->logger->error('A referenced question has problems loading!');
      $answered = TRUE;
    }
    catch (EntityMalformedException) {
      $this->logger->error('A referenced question has inconsistent data.');
      $answered = TRUE;
    }

    // If not all questions are answered, the lecture can not be completed.
    if (!$answered) {
      throw new BadRequestHttpException('Creative has not answered all required questions.');
    }

    try {
      // Set completed timestamp.
      $timestamp = $this->progressManager->getRequestTime();
      if (!$progress->getCompletedTime()) {
        $progress->setCompletedTime($timestamp);
        $progress->save();
      }

      // Load the respective course progress.
      /** @var \Drupal\courses\Entity\Course $course */
      $course = $entity->getParentEntity();
      $course_progress = $this->progressManager->loadProgress($course);

      // This should never happen. We create the course progress if not present.
      // A sneaky way to ensure that the course progress exists before the
      // lecture and course progresses interfere.
      // @todo Adjust langcode.
      if (!$course_progress instanceof ProgressInterface) {
        $course_progress = CourseProgress::create([
          'course' => $entity->getParentEntity()->id(),
          'uid' => $progress->getOwnerId(),
          'enrolled' => $progress->getEnrollmentTime(),
          'accessed' => $progress->getEnrollmentTime(),
          'langcode' => 'de',
        ]);
        $course_progress->save();
      }

      // Set the parent course completed if this is the last lecture.
      if (
        !$course_progress->getCompletedTime() &&
        $this->progressManager->isLastLecture($entity)
      ) {
        $course_progress->setCompletedTime($timestamp);
        $course_progress->save();
      }
    }
    catch (EntityStorageException | InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new UnprocessableEntityHttpException('The progress of the referenced course has inconsistent persistent data.', $e);
    }

    return new ModifiedResourceResponse(NULL, 201);
  }

}
