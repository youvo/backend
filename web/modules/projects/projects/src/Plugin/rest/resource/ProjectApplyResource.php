<?php

namespace Drupal\projects\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Error;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
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

  use ProjectActionRestResourceRoutesTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

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
    elseif ($project->isApplicant($this->currentUser)) {
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
   */
  public function post(Project $project) {

    // Is the project open?
    if (!$project->workflowManager()->isOpen()) {
      return new ModifiedResourceResponse('Project is not open to apply.', 403);
    }
    // Did creative already apply to project?
    elseif ($project->isApplicant($this->currentUser)) {
      return new ModifiedResourceResponse('Creative already applied to project.', 403);
    }
    // Otherwise, project is open to apply for creative.
    else {
      $project->appendApplicant($this->currentUser);
      try {
        $project->save();
      }
      catch (EntityStorageException $e) {
        $variables = Error::decodeException($e);
        $this->logger->error('%type: @message in %function (line %line of %file). Unable to save project.', $variables);
        throw new UnprocessableEntityHttpException('Could not save project.');
      }
      return new ModifiedResourceResponse('Added creative to applicants.', 201);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function routes() {
    return $this->routesWithAccessCallback('accessProjectApply');
  }

}
