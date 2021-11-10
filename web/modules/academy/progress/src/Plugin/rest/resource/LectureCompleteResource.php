<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\lectures\Entity\Lecture;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides Progress Lecture Complete Resource.
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
   * Responds POST requests.
   *
   * @param \Drupal\lectures\Entity\Lecture $entity
   *   The referenced lecture.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(Lecture $entity) {

    try {
      // Get the respective lecture progress by lecture and current user.
      $progress = $this->progressManager->getProgress($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new HttpException(417, 'The progress of the requested lecture has inconsistent persistent data.', $e);
    }

    // There is no progress for this lecture by this user.
    if (empty($progress)) {
      throw new BadRequestHttpException('Creative is not enrolled in this lecture.');
    }

    try {
      // Set completed timestamp.
      $timestamp = $this->progressManager->getRequestTime();
      $progress->setCompletedTime($timestamp);
      $progress->save();

      // Also set the parent course completed if this is the last lecture.
      if ($course_progress = $this->progressManager->isLastLecture($entity)) {
        if (!$course_progress->getCompletedTime()) {
          $course_progress->setCompletedTime($timestamp);
          $course_progress->save();
        }
      }
    }
    catch (EntityStorageException | InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new HttpException(417, 'The progress of the referenced course has inconsistent persistent data.', $e);
    }

    return new ModifiedResourceResponse(NULL, 201);
  }

}
