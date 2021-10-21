<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\lectures\Entity\Lecture;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpFoundation\Request;
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(Lecture $lecture, Request $request) {

    // Get the respective lecture progress by lecture and current user.
    $progress = $this->getRespectiveLectureProgress($lecture);

    // There is no progress for this lecture by this user.
    if (empty($progress)) {
      throw new BadRequestHttpException('Creative is not enrolled in this lecture.');
    }

    try {
      // Set completed timestamp.
      $progress->setCompletedTime($this->time->getRequestTime());
      $progress->save();
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    return new ModifiedResourceResponse();
  }

}
