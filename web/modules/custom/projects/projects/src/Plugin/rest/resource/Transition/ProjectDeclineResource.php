<?php

namespace Drupal\projects\Plugin\rest\resource\Transition;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\Plugin\rest\resource\ProjectMailRequestTrait;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project decline resource.
 *
 * @RestResource(
 *   id = "project:decline",
 *   label = @Translation("Project Decline Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/decline"
 *   }
 * )
 */
class ProjectDeclineResource extends ProjectResourceBase {

  use ProjectMailRequestTrait;

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // Archived projects don't transition, also not for administrators.
    if ($project->isArchived()) {
      return AccessResult::forbidden('The project is archived.')
        ->addCacheableDependency($project);
    }

    // The user may be permitted to bypass access control.
    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    if ($account->hasPermission(WorkflowPermissions::bypassTransition($workflow_id))) {
      return AccessResult::allowed()
        ->addCacheableDependency($project)
        ->cachePerPermissions();
    }

    // The user requires the permission to initiate this transition.
    $permission = WorkflowPermissions::useTransition($workflow_id, ProjectTransition::Decline->value);
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // Only the manager of the organization declines its projects.
    $organization = $project->getOwner();
    $access_project = AccessResult::allowedIf($organization->isManager($account))
      ->addCacheableDependency($project)
      ->addCacheableDependency($organization)
      ->cachePerUser();
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this transition are not met.');
    }

    return $access_result->andIf($access_project);
  }

  /**
   * Responds to GET requests with the decline reasons.
   */
  public function get(ProjectInterface $project): ResourceResponseInterface {

    $reasons = array_map(static fn (ProjectDeclineReason $reason) => [
      'value' => $reason->value,
      'label' => (string) $reason->label(),
    ], ProjectDeclineReason::cases());

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
        'subject' => 'The subject of the rejection mail, together with body.',
        'body' => 'The plain text of the rejection mail, together with subject.',
      ],
    ]);
    $response->addCacheableDependency($project);

    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * Without subject and body the project is declined without a mail.
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    $content = Json::decode($request->getContent()) ?? [];
    if (!is_array($content)) {
      throw new BadRequestHttpException('The request body is malformed.');
    }

    $event = new ProjectDeclineEvent($project);
    $event->setReason($this->reason($content));
    if ($mail = $this->mail($content)) {
      $event->setMail(...$mail)->setSender($this->currentUser);
    }

    try {
      $this->eventDispatcher->dispatch($event);
    }
    catch (LifecycleTransitionException) {
      throw new ConflictHttpException('Project can not be declined.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project decline failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project declined.');
  }

  /**
   * Gets the decline reason of the request, custom by default.
   */
  protected function reason(array $content): ProjectDeclineReason {
    if (!isset($content['reason'])) {
      return ProjectDeclineReason::Custom;
    }
    $reason = is_string($content['reason']) ? ProjectDeclineReason::tryFrom($content['reason']) : NULL;
    if ($reason === NULL) {
      throw new BadRequestHttpException('The reason is unknown.');
    }
    return $reason;
  }

}
