<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Project Apply Resource.
 *
 * @RestResource(
 *   id = "project:apply",
 *   label = @Translation("Project Apply Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/projects/{project}/apply"
 *   }
 * )
 */
class ProjectApplyResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
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
      $container->get('current_user')
    );
  }

  /**
   * Responds GET requests.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The referenced project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   */
  public function get(ProjectInterface $project) {

    // Is the project open?
    if (!$project->workflowManager()->isOpen()) {
      return new ModifiedResourceResponse('Project is not open to apply.', 403);
    }
    // Did creative already apply to project?
    elseif (array_key_exists($this->currentUser->id(), $project->getApplicantsAsArray())) {
      return new ModifiedResourceResponse('Creative already applied to project.', 403);
    }
    // Otherwise, project is open to apply for creative.
    else {
      return new ModifiedResourceResponse('Creative can apply to project.', 200);
    }
  }

  /**
   * Responds POST requests.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The referenced project.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(Project $project) {

    // Is the project open?
    if (!$project->workflowManager()->isOpen()) {
      return new ModifiedResourceResponse('Project is not open to apply.', 403);
    }
    // Did creative already apply to project?
    elseif (array_key_exists($this->currentUser->id(), $project->getApplicantsAsArray())) {
      return new ModifiedResourceResponse('Creative already applied to project.', 403);
    }
    // Otherwise, project is open to apply for creative.
    else {
      $creative_id = $this->currentUser->id();
      $project->appendApplicant($creative_id);
      $project->save();
      return new ModifiedResourceResponse('Added creative to applicants.', 201);
    }
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
      $route->setRequirement('_custom_access', '\Drupal\projects\Controller\ProjectAccessController::accessProjectApply');
      $parameters = $route->getOption('parameters') ?: [];
      $route->setOption('parameters', $parameters + [
        'project' => [
          'type' => 'entity:node',
          'converter' => 'paramconverter.uuid',
        ],
      ]);
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
