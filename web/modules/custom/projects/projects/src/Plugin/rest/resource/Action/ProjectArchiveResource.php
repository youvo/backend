<?php

namespace Drupal\projects\Plugin\rest\resource\Action;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\Exception\ProjectActionException;
use Drupal\projects\Plugin\rest\resource\ProjectMailRequestTrait;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectInterface;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project archive resource.
 */
#[RestResource(
  id: 'project:archive',
  label: new TranslatableMarkup('Project Archive Resource'),
  uri_paths: [
    'canonical' => '/api/projects/{project}/archive',
  ],
)]
class ProjectArchiveResource extends ProjectResourceBase {

  use ProjectMailRequestTrait;

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // Archived projects don't change, also not for administrators.
    if ($project->isArchived()) {
      return AccessResult::forbidden('The project is archived.')
        ->addCacheableDependency($project);
    }

    // Administrators archive projects in any state.
    if ($account->hasPermission('archive projects in any state')) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerPermissions();
    }

    // The user requires the permission to do this action.
    $access_result = AccessResult::allowedIfHasPermission($account, 'archive projects');

    // Others archive open, ongoing or completed projects.
    $lifecycle = $project->lifecycle();
    $access_project = AccessResult::allowedIf($lifecycle->isOpen() || $lifecycle->isOngoing() || $lifecycle->isCompleted())
      ->addCacheableDependency($project);
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this action are not met.');
    }

    return $access_result->andIf($access_project);
  }

  /**
   * Responds to GET requests with the archive reasons.
   */
  public function get(ProjectInterface $project): ResourceResponseInterface {

    $reasons = array_map(static fn (ProjectArchiveReason $reason) => [
      'value' => $reason->value,
      'label' => (string) $reason->label(),
    ], ProjectArchiveReason::cases());

    $response = new ResourceResponse([
      'resource' => str_replace(':', '.', $this->pluginId),
      'data' => [
        'type' => 'project',
        'id' => $project->uuid(),
        'title' => $project->getTitle(),
        'reasons' => $reasons,
      ],
      'post_optional' => [
        'reason' => 'One of the reason values, default custom.',
        'subject' => 'The subject of the mail, together with body. Only open, ongoing and completed projects are mailed.',
        'body' => 'The plain text of the mail, together with subject.',
      ],
    ]);
    $response->addCacheableDependency($project);

    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * Without subject and body the project is archived without a mail.
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    $content = Json::decode($request->getContent()) ?? [];
    if (!is_array($content)) {
      throw new BadRequestHttpException('The request body is malformed.');
    }

    $event = new ProjectArchiveEvent($project);
    $event->setReason($this->reason($content));
    if ($mail = $this->mail($content)) {
      $event->setMail(...$mail)->setSender($this->currentUser);
    }

    try {
      $this->eventDispatcher->dispatch($event);
    }
    catch (ProjectActionException) {
      throw new ConflictHttpException('Project can not be archived.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project archive failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project archived.');
  }

  /**
   * Gets the archive reason of the request, custom by default.
   */
  protected function reason(array $content): ProjectArchiveReason {
    if (!isset($content['reason'])) {
      return ProjectArchiveReason::Custom;
    }
    $reason = is_string($content['reason']) ? ProjectArchiveReason::tryFrom($content['reason']) : NULL;
    if ($reason === NULL) {
      throw new BadRequestHttpException('The reason is unknown.');
    }
    return $reason;
  }

}
