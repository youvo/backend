<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileStorageInterface;
use Drupal\projects\Entity\ProjectComment;
use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\projects\ProjectInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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

  /**
   * Serialization with Json.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected SerializationInterface $serializationJson;

  /**
   * The file storage.
   *
   * @var \Drupal\file\FileStorageInterface
   */
  protected FileStorageInterface $fileStorage;

  /**
   * Constructs a ProjectMediateResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Component\Serialization\SerializationInterface $serialization_json
   *   Serialization with Json.
   * @param \Drupal\file\FileStorageInterface $file_storage
   *   The file storage.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountInterface $current_user,
    EventDispatcherInterface $event_dispatcher,
    RouteProviderInterface $route_provider,
    SerializationInterface $serialization_json,
    FileStorageInterface $file_storage
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $current_user, $event_dispatcher, $route_provider);
    $this->serializationJson = $serialization_json;
    $this->fileStorage = $file_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('projects'),
      $container->get('current_user'),
      $container->get('event_dispatcher'),
      $container->get('router.route_provider'),
      $container->get('serialization.json'),
      $container->get('entity_type.manager')->getStorage('file')
    );
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
   *   Thrown if unable to save project.
   */
  public function post(ProjectInterface $project, Request $request) {

    // Decode content of the request.
    $request_body = $this->serializationJson->decode($request->getContent());
    $comment = $request_body['comment'] ?? '';
    $results = $request_body['results'] ?? [];

    // Prepare data.
    $this->validateComment($comment);
    $this->validateResults($results);
    $this->preloadFiles($results);
    [$result_files, $result_links] = $this->shapeResults($results);

    if ($project->lifecycle()->complete()) {
      $result = $project->getResult();
      if (!empty($comment)) {
        $comment_object = ProjectComment::create([
          'value' => $comment,
          'project_result' => $result->id(),
        ]);
        $comment_object->save();
        $result->appendComment($comment_object);
      }
      $result->setFiles($result_files);
      $result->setLinks($result_links);
      $result->save();
      $project->save();
      $this->eventDispatcher->dispatch(new ProjectCompleteEvent($project));
      return new ModifiedResourceResponse('Project completed.');
    }
    else {
      throw new ConflictHttpException('Project can not be completed.');
    }

  }

  /**
   * Validates results entries.
   */
  protected function validateComment(mixed $comment): void {
    if (!is_string($comment)) {
      throw new BadRequestHttpException('Malformed request body. The comment is not a string.');
    }
  }

  /**
   * Validates results entries.
   */
  protected function validateResults(array $results): void {
    foreach ($results as $result) {
      if (!array_key_exists('type', $result) ||
        !array_key_exists('value', $result) ||
        !array_key_exists('description', $result)) {
        throw new BadRequestHttpException('Malformed request body. A result does not define type, value or description.');
      }
      if ($result['type'] == 'file' && !Uuid::isValid($result['value'])) {
        throw new BadRequestHttpException('Malformed request body. A file result has an invalid UUID.');
      }
      if ($result['type'] == 'link' && !is_string($result['value'])) {
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
    $file_uuids = array_column(array_filter($results, fn($r) => $r['type'] == 'file'), 'value');
    $files = $this->fileStorage
      ->loadByProperties(['uuid' => array_unique($file_uuids)]);

    // Populate results with files.
    foreach ($results as $delta => $result) {
      if ($result['type'] == 'file') {
        $matching_file = array_filter($files, fn($f) => $f->uuid() == $result['value']);
        $results[$delta]['value'] = reset($matching_file);
      }
    }
  }

  /**
   * Shapes the results as required by the fields.
   */
  protected function shapeResults(array $results): array {
    foreach (array_values($results) as $delta => $result) {
      if ($result['type'] == 'file') {
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

      if ($result['type'] == 'link') {
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
