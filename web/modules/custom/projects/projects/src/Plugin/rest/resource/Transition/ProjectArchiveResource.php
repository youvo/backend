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
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project archive resource.
 *
 * @RestResource(
 *   id = "project:archive",
 *   label = @Translation("Project Archive Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/archive"
 *   }
 * )
 */
class ProjectArchiveResource extends ProjectTransitionResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // The user may be permitted to bypass access control.
    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    $bybass_permission = WorkflowPermissions::bypassTransition($workflow_id);
    if ($account->hasPermission($bybass_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // The user requires the permission to initiate this transition.
    $permission = WorkflowPermissions::useTransition($workflow_id, ProjectTransition::Archive->value);
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // The resource should define project-dependent access conditions.
    $project_condition = $project->isPublished() && $project->getOwner()->isManager($account);
    $access_project = AccessResult::allowedIf($project_condition)
      ->addCacheableDependency($project)
      ->addCacheableDependency($project->getOwner())
      ->cachePerUser();
    if ($access_project instanceof AccessResultReasonInterface) {
      $access_project->setReason('The project conditions for this transition are not met.');
    }

    return $access_result->andIf($access_project);
  }

  /**
   * Responds to POST requests.
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    $content = Json::decode($request->getContent()) ?? [];
    $reason = $this->validateRequestContent($content);

    try {
      $event = new ProjectArchiveEvent($project);
      $event->setReason($reason);
      $event->setMessage($content['message'] ?? '');
      $this->eventDispatcher->dispatch($event);
    }
    catch (LifecycleTransitionException) {
      throw new ConflictHttpException('Project can not be archived.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project archive failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project archived.');
  }

  /**
   * Validates the request content.
   *
   * Both the reason and the message are optional.
   *
   * @return \Drupal\projects\ProjectArchiveReason|null
   *   The reason for archiving the project, if provided.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   */
  protected function validateRequestContent(mixed $content): ?ProjectArchiveReason {

    if (!is_array($content)) {
      throw new BadRequestHttpException('The request body is malformed.');
    }

    if (isset($content['message']) && !is_string($content['message'])) {
      throw new BadRequestHttpException('The message in the request body must be a string.');
    }

    if (!isset($content['reason'])) {
      return NULL;
    }

    $reason = is_string($content['reason']) ? ProjectArchiveReason::tryFrom($content['reason']) : NULL;
    if ($reason === NULL) {
      $options = implode(', ', array_column(ProjectArchiveReason::cases(), 'value'));
      throw new BadRequestHttpException('The reason in the request body is not valid. Valid reasons are: ' . $options . '.');
    }

    return $reason;
  }

}
