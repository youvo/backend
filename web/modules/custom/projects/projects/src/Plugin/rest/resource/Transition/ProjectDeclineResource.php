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
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
class ProjectDeclineResource extends ProjectTransitionResourceBase {

  /**
   * The maximum length of the subject.
   */
  protected const int SUBJECT_MAX_LENGTH = 255;

  /**
   * The maximum length of the body.
   */
  protected const int BODY_MAX_LENGTH = 10000;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function access(AccountInterface $account, ProjectInterface $project): AccessResultInterface {

    // The user may be permitted to bypass access control.
    $workflow_id = ProjectLifecycle::WORKFLOW_ID;
    if ($account->hasPermission(WorkflowPermissions::bypassTransition($workflow_id))) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // The user requires the permission to initiate this transition.
    $permission = WorkflowPermissions::useTransition($workflow_id, ProjectTransition::Decline->value);
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // Only the manager of the organization declines its projects.
    $organization = $project->getOwner();
    $project_condition = $project->isPublished() && $organization->isManager($account);
    $access_project = AccessResult::allowedIf($project_condition)
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

    return new ResourceResponse('Project declined.');
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

  /**
   * Gets the subject and body of the request, or NULL if there is no mail.
   *
   * @return array{0: string, 1: string}|null
   *   The subject and the body.
   */
  protected function mail(array $content): ?array {
    $subject = $content['subject'] ?? NULL;
    $body = $content['body'] ?? NULL;
    if ($subject === NULL && $body === NULL) {
      return NULL;
    }
    if (!is_string($subject) || !is_string($body) || trim($subject) === '' || trim($body) === '') {
      throw new BadRequestHttpException('The mail needs both a subject and a body.');
    }
    if (mb_strlen($subject) > static::SUBJECT_MAX_LENGTH || mb_strlen($body) > static::BODY_MAX_LENGTH) {
      throw new BadRequestHttpException('The subject or the body of the mail is too long.');
    }
    return [$subject, $body];
  }

}
