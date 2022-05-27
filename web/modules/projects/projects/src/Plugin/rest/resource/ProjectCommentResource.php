<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\projects\Entity\ProjectComment;
use Drupal\projects\Event\ProjectCommentEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides Project Comment Resource.
 *
 * @RestResource(
 *   id = "project:comment",
 *   label = @Translation("Project Comment Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/comment"
 *   }
 * )
 */
class ProjectCommentResource extends ProjectActionResourceBase {

  /**
   * Responds to GET requests.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function get(ProjectInterface $project) {

    // There already exists a comment for this user.
    $comments = $project->getResult()->getComments();
    if (!empty(array_filter($comments, fn($c) => $c->getOwnerId() == $this->currentUser->id()))) {
      return new ModifiedResourceResponse('User already commented on this project.', 403);
    }

    // Otherwise, project is open to comment for user.
    else {
      return new ModifiedResourceResponse('User can comment on this project.', 200);
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
   */
  public function post(ProjectInterface $project, Request $request) {

    // There already exists a comment for this user.
    $project_result = $project->getResult();
    $comments = $project_result->getComments();
    if (!empty(array_filter($comments, fn($c) => $c->getOwnerId() == $this->currentUser->id()))) {
      return new ModifiedResourceResponse('User already commented on this project.', 403);
    }

    // Otherwise, project is open to apply for creative.
    else {

      // Decode content of the request.
      $content = $this->serializationJson->decode($request->getContent());
      $comment = $content['comment'] ?? '';

      // Validate comment.
      if (!is_string($comment)) {
        throw new BadRequestHttpException('Malformed request body. The comment is not a string.');
      }

      // Only add new comment if non-empty.
      if (!empty($comment)) {

        // Create new project comment and append to project result.
        $comment_object = ProjectComment::create([
          'value' => $comment,
          'project_result' => $project_result->id(),
        ]);
        $comment_object->save();
        $project_result->appendComment($comment_object);
        $project_result->save();
        $project->save();

        // Dispatch project comment event.
        $event = new ProjectCommentEvent($project);
        $event->setComment($comment);
        $this->eventDispatcher->dispatch($event);

        return new ModifiedResourceResponse('Added comment to project results.', 200);
      }

      return new ModifiedResourceResponse('Not added. The provided comment was empty.', 200);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    return $this->routesWithAccessCallback('accessComment');
  }

}
