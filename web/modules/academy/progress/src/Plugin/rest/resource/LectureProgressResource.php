<?php

namespace Drupal\progress\Plugin\rest\resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\lectures\Entity\Lecture;
use Drupal\progress\Entity\LectureProgress;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Abstract for Lecture Progress Resources.
 */
abstract class LectureProgressResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a QuestionSubmissionResource object.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * Responds GET requests.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The referenced lecture.
   *
   * @return \Drupal\rest\ResourceResponse|ModifiedResourceResponse
   *   Response.
   */
  public function get(Lecture $lecture) {

    // Get the respective lecture progress by lecture and current user.
    $progress = $this->getRespectiveLectureProgress($lecture);

    // There is no progress for this lecture by this user.
    if (empty($progress)) {
      return new ModifiedResourceResponse(NULL, 204);
    }

    // Fetch progress information.
    $data['enrolled'] = $progress->getEnrollmentTime();
    $data['accessed'] = $progress->getAccessTime();
    $data['completed'] = $progress->getCompletedTime();

    // Compile response with structured data.
    $response = new ResourceResponse([
      'type' => strtr($this->pluginId, ':', '.') . '.resource',
      'data' => $data,
    ]);

    // Add cacheable dependency to refresh response when lecture is udpated.
    $response->addCacheableDependency($progress);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = strtr($this->pluginId, ':', '.');

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', '\Drupal\progress\Controller\LectureProgressAccessController::accessLecture');
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'lecture' => [
          'type' => 'entity:lecture',
          'converter' => 'paramconverter.uuid',
        ],
      ]);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

  /**
   * Gets the respective progress of the lecture by the current user.
   *
   * @param \Drupal\lectures\Entity\Lecture $lecture
   *   The requested lecture.
   *
   * @returns \Drupal\progress\Entity\LectureProgress|null
   *   The respective progress or NULL if no storage.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  protected function getRespectiveLectureProgress(Lecture $lecture): ?LectureProgress {
    try {
      // Get referenced LectureProgress.
      $query = $this->entityTypeManager
        ->getStorage('lecture_progress')
        ->getQuery();
      $progress_id = $query->condition('lecture', $lecture->id())
        ->condition('uid', $this->currentUser->id())
        ->execute();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    // Return nothing if there is no progress.
    if (empty($progress_id)) {
      return NULL;
    }

    // Something went wrong here.
    if (count($progress_id) > 1) {
      throw new HttpException(417, 'The progress of the requested lecture has inconsistent persistent data.');
    }

    try {
      // Return loaded progress.
      /** @var \Drupal\progress\Entity\LectureProgress $progress */
      $progress = $this->entityTypeManager
        ->getStorage('lecture_progress')
        ->load(reset($progress_id));
      return $progress;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }
  }

}
