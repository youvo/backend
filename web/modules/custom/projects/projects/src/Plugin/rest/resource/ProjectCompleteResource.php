<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\projects\ProjectInterface;
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
   */
  public function post(ProjectInterface $project, Request $request): ResourceResponseInterface {

    $content = Json::decode($request->getContent());
    $this->validateRequestContent($content);

    try {
      $results = $content['results'] ?? [];
      $this->preloadFiles($results);
      [$result_files, $result_links] = $this->shapeResults($results);
      $event = new ProjectCompleteEvent($project);
      $event->setFiles($result_files);
      $event->setLinks($result_links);
      $this->eventDispatcher->dispatch(new ProjectCompleteEvent($project));
    }
    catch (LifecycleTransitionException | InvalidPluginDefinitionException | PluginNotFoundException) {
      throw new ConflictHttpException('Project can not be completed.');
    }
    catch (\Throwable) {
    }

    return new ModifiedResourceResponse('Project completed.');
  }

  /**
   * Validates the request body.
   */
  protected function validateRequestContent(array $content): void {
    $results = $content['results'] ?? [];
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
        ->loadByProperties(['uuid' => array_unique($file_uuids)]);
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
