<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Project Apply Resource.
 *
 * @RestResource(
 *   id = "project:apply",
 *   label = @Translation("Project Apply Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/apply"
 *   }
 * )
 */
class ProjectApplyResource extends ProjectActionResourceBase {

  /**
   * Responds to GET requests.
   */
  public function get(ProjectInterface $project): ResourceResponseInterface {

    // Is the project open?
    if (!$project->lifecycle()->isOpen()) {
      return new ModifiedResourceResponse('Project is not open to apply.', 403);
    }

    // Did creative already apply to project?
    if ($project->isApplicant($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already applied to project.', 403);
    }

    // Otherwise, project is open to apply for creative.
    return new ModifiedResourceResponse('Creative can apply to project.', 200);
  }

  /**
   * Responds to POST requests.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    // Is the project open?
    if (!$project->lifecycle()->isOpen()) {
      return new ModifiedResourceResponse('Project is not open to apply.', 403);
    }

    // Did creative already apply to project?
    if ($project->isApplicant($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already applied to project.', 403);
    }

    // Otherwise, project is open to apply for creative.
    /** @var \Drupal\creatives\Entity\Creative $applicant */
    $applicant = $this->entityTypeManager->getStorage('user')->load($this->currentUser->id());

    // Decode content of the request.
    $content = Json::decode($request->getContent());

    // Add phone number to creative.
    if (!empty($content['phone'])) {
      $applicant->setPhoneNumber($content['phone']);
      $applicant->save();
    }

    // Append applicant to project.
    $project->appendApplicant($applicant);
    $project->save();

    // Dispatch project apply event.
    $event = new ProjectApplyEvent($project);
    $event->setMessage($content['message'] ?? '');
    $event->setPhoneNumber($content['phone'] ?? '');
    $event->setApplicant($applicant);
    $this->eventDispatcher->dispatch($event);

    return new ModifiedResourceResponse('Added creative to applicants.', 200);
  }

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {
    return $this->routesWithAccessCallback('accessApply');
  }

}
