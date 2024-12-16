<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\ResourceResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Provides Project Complete Resource.
 *
 * @RestResource(
 *   id = "project:complete",
 *   label = @Translation("Project Complete Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/complete"
 *   }
 * )
 */
class ProjectCompleteResource extends ProjectTransitionResourceBase {

  protected const TRANSITION = 'complete';

  /**
   * {@inheritdoc}
   */
  protected static function projectAccessCondition(AccountInterface $account, ProjectInterface $project): bool {
    return $project->isAuthor($account) ||
      $project->isParticipant($account) ||
      $project->getOwner()->isManager($account);
  }

  /**
   * Responds to POST requests.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    // Decode content of the request.
    $request_body = Json::decode($request->getContent());
    $results = $request_body['results'] ?? [];

    // Prepare data.
    $this->validateResults($results);
    $this->preloadFiles($results);
    [$result_files, $result_links] = $this->shapeResults($results);

    if ($project->lifecycle()->complete()) {
      $result = $project->getResult();
      $result->setFiles($result_files);
      $result->setLinks($result_links);
      $result->save();
      $project->save();
      $this->eventDispatcher->dispatch(new ProjectCompleteEvent($project));
      return new ModifiedResourceResponse('Project completed.');
    }

    throw new ConflictHttpException('Project can not be completed.');
  }

  /**
   * Validates results entries.
   */
  protected function validateResults(array $results): void {
    foreach ($results as $result) {
      if (
        !array_key_exists('type', $result) ||
        !array_key_exists('value', $result) ||
        !array_key_exists('description', $result)
      ) {
        throw new BadRequestHttpException('Malformed request body. A result does not define type, value or description.');
      }
      if ($result['type'] === 'file' && !Uuid::isValid($result['value'])) {
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
   * Preloads files in results array.
   */
  protected function preloadFiles(array &$results): void {
    $file_uuids = array_unique(array_column(array_filter($results, static fn($r) => $r['type'] === 'file'), 'value'));
    if (!empty($file_uuids)) {
      $files = $this->entityTypeManager
        ->getStorage('file')
        ->loadByProperties(['uuid' => array_unique($file_uuids)]);
      // Populate results with files.
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
