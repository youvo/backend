<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\courses\Entity\Course;
use Drupal\progress\Entity\CourseProgress;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides Progress Lecture Complete Resource.
 *
 * @RestResource(
 *   id = "progress:course:access",
 *   label = @Translation("Progress Course Access Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/courses/{entity}/access"
 *   }
 * )
 */
class CourseAccessResource extends ProgressResource {

  /**
   * Responds POST requests.
   *
   * @param \Drupal\courses\Entity\Course $entity
   *   The referenced course.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function post(Course $entity) {

    try {
      // Get the respective course progress by lecture and current user.
      $progress = $this->progressManager->getProgress($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new HttpException(417, 'The progress of the requested lecture has inconsistent persistent data.', $e);
    }

    // There is no progress for this course by this user.
    // @todo Pass langcode in which course was enrolled.
    if (empty($progress)) {
      $progress = CourseProgress::create([
        'course' => $entity->id(),
        'uid' => $this->progressManager->getCurrentUserId(),
        'accessed' => $this->progressManager->getRequestTime(),
        'langcode' => 'en',
      ]);
    }
    // Update access timestamp for this users progress.
    else {
      $progress->setAccessTime($this->progressManager->getRequestTime());
    }

    // Save progress.
    try {
      $progress->save();
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    return new ModifiedResourceResponse(NULL, 201);
  }

}
