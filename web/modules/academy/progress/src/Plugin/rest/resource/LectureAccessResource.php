<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\Entity\LectureProgress;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides Progress Lecture Complete Resource.
 *
 * @RestResource(
 *   id = "progress:lecture:access",
 *   label = @Translation("Progress Lecture Access Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/lectures/{lecture}/access"
 *   }
 * )
 */
class LectureAccessResource extends LectureProgressResource {

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

    // Get the respective lecture progress by lecture and current user.
    $progress = $this->getRespectiveLectureProgress($lecture);

    // There is no progress for this lecture by this user.
    // @todo Pass langcode in which lecture was enrolled.
    if (empty($progress)) {
      $progress = LectureProgress::create([
        'lecture' => $lecture->id(),
        'uid' => $this->currentUser->id(),
        'langcode' => 'en',
        'enrolled' => $this->time->getRequestTime(),
        'accessed' => $this->time->getRequestTime(),
      ]);
    }
    // Update access timestamp for this users progress.
    else {
      try {
        $progress->setAccessTime($this->time->getRequestTime());
        $progress->save();
      }
      catch (EntityStorageException $e) {
        throw new HttpException(500, 'Internal Server Error', $e);
      }
    }

    return new ModifiedResourceResponse(NULL, 201);
  }

}
