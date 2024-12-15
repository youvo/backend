<?php

namespace Drupal\feedback\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\feedback\Entity\Feedback;
use Drupal\feedback\Event\FeedbackCreateEvent;
use Drupal\feedback\FeedbackInterface;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponseInterface;
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
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    return $instance;
  }

  /**
   * Responds to POST requests.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(ProjectInterface $project): ResourceResponseInterface {

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
      // Maybe the feedback was already completed.
      if ($feedback->isCompleted()) {
        return new ModifiedResourceResponse('Feedback already completed!', 409);
      }
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
  public function routes(): RouteCollection {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->pluginId);

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
