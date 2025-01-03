<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\Entity\LectureProgress;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides progress lecture complete resource.
 *
 * @RestResource(
 *   id = "progress:lecture:access",
 *   label = @Translation("Progress Lecture Access Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/lectures/{entity}/access"
 *   }
 * )
 */
class LectureAccessResource extends ProgressResource {

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
    // @todo Pass langcode in which lecture was enrolled.
    if ($progress === NULL) {
      $progress = LectureProgress::create([
        'lecture' => $entity->id(),
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
