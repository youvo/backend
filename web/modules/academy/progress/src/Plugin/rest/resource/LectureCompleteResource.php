<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\lectures\Entity\Lecture;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;

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
   * Responds GET requests.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The referenced lecture.
   *
   * @return \Drupal\rest\ResourceResponse|ModifiedResourceResponse
   *   Response.
   */
  public function get(Lecture $lecture) {

    // Get the respective lecture progress by lecture and current user.
    $progress = $this->getRespectiveLectureProgress($lecture);

    // There is no progress for this lecture by this user.
    if (empty($progress)) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    // Fetch progress information.
    $data['enrolled'] = $progress->getEnrollmentTime();
    $data['accessed'] = $progress->getAccessTime();
    $data['completed'] = $progress->getCompletedTime();

    // Compile response with structured data.
    $response = new ResourceResponse([
      'type' => 'progress.lecture.complete.resource',
      'data' => $data,
    ]);

    // Add cacheable dependency to refresh response when lecture is udpated.
    $response->addCacheableDependency($progress);

    return $response;
  }

  /**
   * Responds POST requests.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The referenced lecture.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function post(Lecture $lecture, Request $request) {

    // Decode content of the request.
    $request_content = $this->serializationJson
      ->decode($request->getContent());

    return new ResourceResponse('Hello POST.', 200);
  }

}
