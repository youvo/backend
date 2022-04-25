<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\ProjectWorkflowManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides Project Mediate Resource.
 *
 * @RestResource(
 *   id = "project:submit",
 *   label = @Translation("Project Submit Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/submit"
 *   }
 * )
 */
class ProjectSubmitResource extends ResourceBase {

  use ProjectTransitionRestResourceRoutesTrait;

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The referenced project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project) {

    if (!$project->workflowManager()
      ->canTransitionByLabel(ProjectWorkflowManager::TRANSITION_SUBMIT)) {
      throw new ConflictHttpException('Project can not be published.');
    }

    $project->workflowManager()->transitionPublish();
    $project->save();

    return new ModifiedResourceResponse('Project submitted.');
  }

}
