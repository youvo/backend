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
 *     "canonical" = "/api/lectures/{lecture}/complete"
 *   }
 * )
 */
class LectureCompleteResource extends LectureProgressResource {

  /**
   * Responds POST requests.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The referenced lecture.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(Lecture $lecture) {

    try {
      // Get the respective lecture progress by lecture and current user.
      $progress = $this->progressManager->getProgress($lecture);
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
      $progress->setCompletedTime($this->progressManager->getRequestTime());
      $progress->save();
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    return new ModifiedResourceResponse(NULL, 201);
  }

}
