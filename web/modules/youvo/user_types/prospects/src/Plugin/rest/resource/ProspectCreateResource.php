<?php

namespace Drupal\prospects\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Prospect Create Resource.
 *
 * @RestResource(
 *   id = "prospect:create",
 *   label = @Translation("Prospect Create Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/prospects/create"
 *   }
 * )
 */
class ProspectCreateResource extends ResourceBase {

  /**
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $serializationJson;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * @param \Drupal\Component\Serialization\Json $serialization_json
   *   The serialization by Json service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, Json $serialization_json, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializationJson = $serialization_json;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('serialization.json'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds GET requests.
   *
    * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get(Request $request) {

    // Decode content of the request.
    $request_content = $this->serializationJson->decode($request->getContent());

    // Check whether email was provided.
    if (empty($request_content['email'])) {
      throw new BadRequestHttpException('Body requires email value.');
    }

    // Check whether there exists an account for the given email.
    try {
      if ($this->accountExistsForEmail($request_content['email'])) {
        return new ModifiedResourceResponse('There already exists an account for the provided email address.', 409);
      }
    }
    catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    // There is no account registered for this email - success.
    return new ModifiedResourceResponse();
  }

  /**
   * Responds POST requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   Response.
   */
  public function post(Request $request) {

    // Decode content of the request.
    $request_content = $this->serializationJson
      ->decode($request->getContent());

    if (empty($request_content)) {
      throw new BadRequestHttpException('Malformed request body.');
    }

    // @todo Create new prospect organization user.

    // @todo Create new project proposal entity.

    return new ModifiedResourceResponse();
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
      $route->setRequirement('_custom_access', '\Drupal\prospects\Controller\ProspectAccessController::accessProspectCreate');
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

  /**
   * Check whether email used by already existing account.
   *
   * @param string $mail
   *   The email in question.
   *
   * @return bool
   *   Whether an account already exists.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function accountExistsForEmail(string $mail) {
    return !empty($this->entityTypeManager->getStorage('user')
      ->loadByProperties(['mail' => $mail]));
  }
}
