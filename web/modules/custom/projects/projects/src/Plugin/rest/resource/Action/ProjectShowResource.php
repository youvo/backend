<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Event\ProjectShowEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project show resource.
 *
 * @RestResource(
 *   id = "project:show",
 *   label = @Translation("Project Show Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/show"
 *   }
 * )
 */
class ProjectShowResource extends ProjectActionResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'administer projects');
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {
    try {
      $this->eventDispatcher->dispatch(new ProjectShowEvent($project));
    }
    catch (\LogicException) {
      throw new ConflictHttpException('Project can not be shown.');
    }
    catch (\Throwable) {
    }
    return new ModifiedResourceResponse('Project shown.');
  }

}
