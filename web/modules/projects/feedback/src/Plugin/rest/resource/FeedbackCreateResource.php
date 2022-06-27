<?php

namespace Drupal\feedback\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\feedback\Entity\Feedback;
use Drupal\feedback\Event\FeedbackCreateEvent;
use Drupal\feedback\FeedbackInterface;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides feedback create resource.
 *
 * @RestResource(
 *   id = "feedback:create",
 *   label = @Translation("Feedback Create Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/feedback"
 *   }
 * )
 */
class FeedbackCreateResource extends ResourceBase {

  /**
   * Constructs a FeedbackCreateResource object.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected EventDispatcherInterface $eventDispatcher
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
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
      $container->get('logger.factory')->get('youvo'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project) {

    try {
      $feedbacks = $this->entityTypeManager->getStorage('feedback')
        ->loadByProperties([
          'project' => $project->id(),
          'author' => $this->currentUser->id(),
        ]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      return new ModifiedResourceResponse('Unable to load feedback.', 500);
    }

    // Create new feedback if there is no existing feedback.
    if (empty($feedbacks)) {
      if ($project->getOwnerId() == $this->currentUser->id()) {
        $bundle = 'organization';
      }
      elseif ($project->getOwner()->isManager($this->currentUser)) {
        // @todo $bundle = 'manager' when form is available.
        $bundle = 'creative';
      }
      else {
        $bundle = 'creative';
      }
      $feedback = Feedback::create([
        'bundle' => $bundle,
        'project' => $project->id(),
        'author' => $this->currentUser->id(),
      ]);
      $feedback->save();
      $data = [
        'id' => $feedback->uuid(),
        'type' => 'feedback--' . $bundle,
      ];
      $this->eventDispatcher->dispatch(new FeedbackCreateEvent($feedback));
      return new ModifiedResourceResponse(['data' => $data], 201);
    }

    // Otherwise, return uuid of existing feedback.
    $feedback = reset($feedbacks);
    if ($feedback instanceof FeedbackInterface) {
      $data = [
        'id' => $feedback->uuid(),
        'type' => 'feedback--' . $feedback->bundle(),
      ];
      return new ModifiedResourceResponse(['data' => $data], 200);
    }

    return new ModifiedResourceResponse('Unable to load feedback.', 422);
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
      $route->setRequirement('_custom_access', '\Drupal\feedback\Access\FeedbackCreateAccess::access');
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'project' => [
          'type' => 'entity:project',
          'converter' => 'paramconverter.uuid',
        ],
      ]);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
