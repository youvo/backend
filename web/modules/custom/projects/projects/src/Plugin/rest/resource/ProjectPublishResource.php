<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides Project Publish Resource.
 *
 * @RestResource(
 *   id = "project:publish",
 *   label = @Translation("Project Publish Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/publish"
 *   }
 * )
 */
class ProjectPublishResource extends ProjectTransitionResourceBase {

  protected const TRANSITION = 'publish';

  /**
   * {@inheritdoc}
   */
  protected static function projectAccessCondition(AccountInterface $account, ProjectInterface $project): bool {
    return $project->getOwner()->isManager($account);
  }

  /**
   * Responds to POST requests.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    if (!$project->lifecycle()->publish()) {
      throw new ConflictHttpException('Project can not be published.');
    }
    $project->save();
    $this->eventDispatcher->dispatch(new ProjectPublishEvent($project));
    return new ModifiedResourceResponse('Project published.');
  }

}
