<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\courses\Entity\Course;
use Drupal\progress\Entity\CourseProgress;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides progress lecture complete resource.
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
   * Responds to POST requests.
   */
  public function post(Course $entity): ResourceResponseInterface {

    try {
      // Get the respective course progress by course and current user.
      $progress = $this->progressManager->loadProgress($entity);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
    catch (EntityMalformedException $e) {
      throw new UnprocessableEntityHttpException('The progress of the requested course has inconsistent persistent data.', $e);
    }

    // There is no progress for this course by this user.
    // @todo Pass langcode in which course was enrolled.
    if ($progress === NULL) {
      $progress = CourseProgress::create([
        'course' => $entity->id(),
        'uid' => $this->progressManager->getCurrentUserId(),
        'accessed' => $this->progressManager->getRequestTime(),
        'langcode' => 'de',
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
