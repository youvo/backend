<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpFoundation\Request;

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
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   */
  public function get(ProjectInterface $project) {

    // Is the project open?
    if (!$project->lifecycle()->isOpen()) {
      return new ModifiedResourceResponse('Project is not open to apply.', 403);
    }

    // Did creative already apply to project?
    elseif ($project->isApplicant($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already applied to project.', 403);
    }

    // Otherwise, project is open to apply for creative.
    else {
      return new ModifiedResourceResponse('Creative can apply to project.', 200);
    }
  }

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if project can not be saved.
   */
  public function post(ProjectInterface $project, Request $request) {

    // Is the project open?
    if (!$project->lifecycle()->isOpen()) {
      return new ModifiedResourceResponse('Project is not open to apply.', 403);
    }

    // Did creative already apply to project?
    elseif ($project->isApplicant($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already applied to project.', 403);
    }

    // Otherwise, project is open to apply for creative.
    else {

      /** @var \Drupal\creatives\Entity\Creative $applicant */
      $applicant = $this->userStorage->load($this->currentUser->id());

      // Decode content of the request.
      $content = $this->serializationJson->decode($request->getContent());

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
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    return $this->routesWithAccessCallback('accessApply');
  }

}
