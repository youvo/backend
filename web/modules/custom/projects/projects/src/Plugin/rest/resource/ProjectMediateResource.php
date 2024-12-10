<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\rest\ResourceResponse;
use Drupal\projects\ProjectInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides Project Mediate Resource.
 *
 * @RestResource(
 *   id = "project:mediate",
 *   label = @Translation("Project Mediate Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/mediate"
 *   }
 * )
 */
class ProjectMediateResource extends ProjectTransitionResourceBase {

  /**
   * Serialization with Json.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected SerializationInterface $serializationJson;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
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
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $current_user, $event_dispatcher, $route_provider);
    $this->serializationJson = $serialization_json;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function get(ProjectInterface $project) {

    // Fetch applicants in desired structure.
    $applicants = [];
    $manager = $project->getOwner()->getManager();
    foreach ($project->getApplicants() as $applicant) {
      if ($applicant->id() != $manager?->id()) {
        $applicants[] = [
          'type' => 'user',
          'id' => $applicant->uuid(),
          'name' => $applicant->getName(),
        ];
      }
    }

    // Compile response with structured data.
    $response = new ResourceResponse([
      'resource' => strtr($this->pluginId, ':', '.'),
      'data' => [
        'type' => 'project',
        'id' => $project->uuid(),
        'title' => $project->getTitle(),
        'applicants' => $applicants,
      ],
      'post_required' => [
        'selected_creatives' => 'Array of uuid\'s of creatives.',
      ],
    ]);

    // Add cacheable dependency to refresh response when project is updated.
    $response->addCacheableDependency($project);

    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if unable to save project.
   */
  public function post(ProjectInterface $project, Request $request) {

    // Decode content of the request.
    $request_content = $this->serializationJson->decode($request->getContent());

    // The selected_creatives are required to process the request.
    if (!array_key_exists('selected_creatives', $request_content)) {
      throw new BadRequestHttpException('Request body does not specify selected_creatives.');
    }

    // Set preliminary selected_creatives variable.
    $selected_creatives = array_unique($request_content['selected_creatives']);

    // Force at least one selected creative.
    if (empty($selected_creatives)) {
      throw new BadRequestHttpException('The selected_creatives array in the request body is empty.');
    }

    // The selected_creatives is expected to be delivered as a simple array.
    if (count(array_filter(array_keys($selected_creatives), 'is_string')) > 0) {
      throw new BadRequestHttpException('The selected_creatives array in the request body is malformed.');
    }

    // The entries of the selected creatives array are expected to be UUIDs.
    if (count(array_filter($selected_creatives, [Uuid::class, 'isValid'])) != count($selected_creatives)) {
      throw new BadRequestHttpException('The entries of the selected_creatives array are not valid UUIDs.');
    }

    // Get applicants for current project and check if selected_creatives are
    // applicable.
    $applicants = $project->getApplicants();
    $applicants_uuids = array_map(fn ($a) => $a->uuid(), $applicants);
    if (count(array_intersect($selected_creatives, $applicants_uuids)) != count($selected_creatives)) {
      throw new UnprocessableEntityHttpException('Some selected creatives did not apply for this project.');
    }

    // Now we are finally sure to mediate the project. We get the UIDs by query.
    try {
      $selected_creatives_ids = $this->entityTypeManager
        ->getStorage('user')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('uuid', $selected_creatives, 'IN')
        ->execute();
      $selected_creatives_ids = array_map('intval', $selected_creatives_ids);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      throw new UnprocessableEntityHttpException('Could not mediate project.', $e);
    }

    // Mediate project with participants.
    if (!empty($selected_creatives_ids) && $project->lifecycle()->mediate()) {

      $project->setPromoted(FALSE);
      $project->setParticipants($selected_creatives_ids);
      if ($manager = $project->getOwner()->getManager()) {
        $project->appendParticipant($manager, 'Manager');
      }
      $project->save();

      $this->eventDispatcher->dispatch(new ProjectMediateEvent($project));

      return new ResourceResponse('Project was mediated successfully.');
    }

    throw new UnprocessableEntityHttpException('Could not mediate project.');
  }

}
