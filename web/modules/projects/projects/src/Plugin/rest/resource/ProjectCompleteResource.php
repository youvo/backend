<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileStorageInterface;
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
    $content = $this->serializationJson->decode($request->getContent());

    // Get the result entity.
    $result = $project->getResult();

    // Populate the result files field.
    if (!empty($content['files'])) {
      $file_uuids = $content['files'];
      if (count(array_filter($file_uuids, [Uuid::class, 'isValid'])) != count($file_uuids)) {
        throw new BadRequestHttpException('The entries of the files array are not valid UUIDs.');
      }
      $files = $this->fileStorage
        ->loadByProperties(['uuid' => array_unique($file_uuids)]);
      $result->setFiles($files);
    }

    // Populate the result files field.
    if (!empty($content['hyperlinks'])) {
      $hyperlinks = $content['hyperlinks'];
      if (count(array_filter($hyperlinks, 'is_string')) != count($hyperlinks)) {
        throw new BadRequestHttpException('The entries of the hyperlinks array are not valid strings.');
      }
      $result->setHyperlinks($hyperlinks);
    }

    if ($project->lifecycle()->complete()) {
      $result->save();
      $project->save();
      $this->eventDispatcher->dispatch(new ProjectCompleteEvent($project));
      return new ModifiedResourceResponse('Project completed.');
    }
    else {
      throw new ConflictHttpException('Project can not be completed.');
    }

  }

}
