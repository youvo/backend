<?php

namespace Drupal\projects\Plugin\rest\resource\Transition;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\file\FileInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\projects\Plugin\rest\resource\ProjectResourceBase;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides project complete resource.
 *
 * @RestResource(
 *   id = "project:complete",
 *   label = @Translation("Project Complete Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/complete"
 *   }
 * )
 */
class ProjectCompleteResource extends ProjectResourceBase {

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
    $permission = WorkflowPermissions::useTransition($workflow_id, ProjectTransition::Complete->value);
    $access_result = AccessResult::allowedIfHasPermission($account, $permission);

    // The author, the participants and the manager complete the project.
    $organization = $project->getOwner();
    $access_project = AccessResult::allowedIf(
      $project->isAuthor($account) ||
      $project->isParticipant($account) ||
      $organization->isManager($account)
    )
      ->addCacheableDependency($project)
      ->addCacheableDependency($organization)
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
    if (!is_array($content)) {
      throw new BadRequestHttpException('The request body is malformed.');
    }
    $this->validateRequestContent($content);

    $results = $content['results'] ?? [];
    $this->preloadFiles($results);
    [$result_files, $result_links] = $this->shapeResults($results);
    $event = new ProjectCompleteEvent($project);
    $event->setFiles($result_files);
    $event->setLinks($result_links);

    try {
      $this->eventDispatcher->dispatch($event);
    }
    catch (LifecycleTransitionException) {
      throw new ConflictHttpException('Project can not be completed.');
    }
    catch (\Throwable $e) {
      $variables = Error::decodeException($e);
      $this->logger->error('Project complete failed unexpectedly. %type: @message in %function (line %line of %file).', $variables);
    }

    return new ModifiedResourceResponse('Project completed.');
  }

  /**
   * Validates the request body.
   */
  protected function validateRequestContent(array $content): void {
    $results = $content['results'] ?? [];
    if (!is_array($results)) {
      throw new BadRequestHttpException('Malformed request body. The results are not an array.');
    }
    foreach ($results as $result) {
      if (
        !is_array($result) ||
        !array_key_exists('type', $result) ||
        !array_key_exists('value', $result) ||
        !array_key_exists('description', $result)
      ) {
        throw new BadRequestHttpException('Malformed request body. A result does not define type, value or description.');
      }
      if ($result['type'] === 'file' && (!is_string($result['value']) || !Uuid::isValid($result['value']))) {
        throw new BadRequestHttpException('Malformed request body. A file result has an invalid UUID.');
      }
      if ($result['type'] === 'link' && !is_string($result['value'])) {
        throw new BadRequestHttpException('Malformed request body. A result link is not a string.');
      }
      if (!is_string($result['description'] ?? '')) {
        throw new BadRequestHttpException('Malformed request body. A result description is not a string.');
      }
    }
  }

  /**
   * Replaces the file UUIDs in the results with the loaded files.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function preloadFiles(array &$results): void {

    $file_uuids = array_unique(array_column(array_filter($results, static fn($r) => $r['type'] === 'file'), 'value'));

    // Populate results with files.
    if (!empty($file_uuids)) {
      $files = $this->entityTypeManager
        ->getStorage('file')
        ->loadByProperties(['uuid' => $file_uuids]);
      foreach ($results as $delta => $result) {
        if ($result['type'] === 'file') {
          $matching_file = array_filter($files, static fn($f) => $f->uuid() === $result['value']);
          $results[$delta]['value'] = reset($matching_file);
        }
      }
    }
  }

  /**
   * Shapes the results as required by the fields.
   */
  protected function shapeResults(array $results): array {

    foreach (array_values($results) as $delta => $result) {
      if ($result['type'] === 'file') {
        // Maybe file was not loaded correctly.
        if (!$result['value'] instanceof FileInterface) {
          continue;
        }
        $result_files[] = [
          'target_id' => $result['value']->id(),
          'weight' => $delta,
          'description' => $result['description'] ?? '',
        ];
      }

      if ($result['type'] === 'link') {
        $result_links[] = [
          'value' => $result['value'],
          'weight' => $delta,
          'description' => $result['description'] ?? '',
        ];
      }
    }

    return [$result_files ?? [], $result_links ?? []];
  }

}
