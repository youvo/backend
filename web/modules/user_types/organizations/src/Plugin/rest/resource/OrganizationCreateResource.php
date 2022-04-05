<?php

namespace Drupal\organizations\Plugin\rest\resource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectRestResponder;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user_bundle\Entity\TypedUser;
use Drupal\youvo\Utility\FieldValidator;
use Drupal\youvo\Utility\RestContentShifter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Organization Create Resource.
 *
 * @RestResource(
 *   id = "organization:create",
 *   label = @Translation("Organization Create Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/organizations/create"
 *   }
 * )
 */
class OrganizationCreateResource extends ResourceBase {

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
   * The email validator service.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * The project REST responder service.
   *
   * @var \Drupal\projects\ProjectRestResponder
   */
  protected $projectRestResponder;

  /**
   * Constructs a OrganizationCreateResource object.
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
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator service.
   * @param \Drupal\projects\ProjectRestResponder $project_rest_responder
   *   The project REST responder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, Json $serialization_json, EntityTypeManagerInterface $entity_type_manager, EmailValidatorInterface $email_validator, ProjectRestResponder $project_rest_responder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializationJson = $serialization_json;
    $this->entityTypeManager = $entity_type_manager;
    $this->emailValidator = $email_validator;
    $this->projectRestResponder = $project_rest_responder;
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
      $container->get('entity_type.manager'),
      $container->get('email.validator'),
      $container->get('project.rest.responder')
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(Request $request) {

    // Create new organization user.
    $organization = TypedUser::create(['type' => 'organization']);
    $attributes = $this->validateAndShiftRequest($request);
    $organization = $this->populateFields($attributes, $organization);
    $organization->addRole('prospect');
    $organization->activate();
    $organization->save();

    // Create new project node.
    $project = Project::create(['type' => 'project']);
    $attributes = $this->projectRestResponder->validateAndShiftRequest($request);
    $project = $this->projectRestResponder->populateFields($attributes, $project);
    $project->get('uid')->value = $organization->id();
    $project->get('field_lifecycle')->value = 'draft';
    $project->get('status')->value = 1;
    $project->save();

    // @todo langcode.

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
      $route->setRequirement('_custom_access', '\Drupal\organizations\Controller\OrganizationAccessController::accessCreate');
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

  /**
   * Create new organization user with some validation.
   *
   * @param array $attributes
   *   Contains organization attributes.
   * @param \Drupal\user_bundle\Entity\TypedUser $organization
   *   The organization.
   *
   * @return \Drupal\user_bundle\Entity\TypedUser
   *   The organization with populated fields.
   */
  protected function populateFields(array $attributes, TypedUser $organization) {

    // Populate fields.
    foreach ($attributes as $field_key => $value) {

      // Validate if field is available. Target field_name instead of name.
      if ($field_key == 'name' || !$organization->hasField($field_key)) {
        $field_key = 'field_' . $field_key;
        if (!$organization->hasField($field_key)) {
          throw new BadRequestHttpException('Malformed request body. Organizations do not provide the field ' . $field_key);
        }
      }

      // Check access to edit field.
      // @todo When field access for organizations is complete.

      // Validate field value.
      $field_definition = $organization->getFieldDefinition($field_key);
      if (!FieldValidator::validate($field_definition, $value)) {
        throw new BadRequestHttpException('Malformed request body. Unable to validate the organization field ' . $field_key);
      }

      // Set the field value.
      $organization->get($field_key)->value = $value;

      // Set username identical to provided mail.
      if ($field_key == 'mail') {
        $organization->setUsername($value);
      }
    }

    return $organization;
  }

  /**
   * Checks and distills values for organizations.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The shifted organization attributes.
   */
  protected function validateAndShiftRequest(Request $request) {

    // Decode content of the request.
    $content = $this->serializationJson->decode($request->getContent());

    // Decline request body without organization data.
    if (empty($content['data']) ||
      !in_array('organization', array_column($content['data'], 'type'))) {
      throw new BadRequestHttpException('Request body does not provide organization.');
    }

    // Get attributes from request content.
    $attributes = RestContentShifter::shiftAttributesByType($content, 'organization');

    // Check if valid email is provided.
    if (empty($attributes['mail']) ||
      !$this->emailValidator->isValid($attributes['mail'])) {
      throw new BadRequestHttpException('Need to provide valid mail to register organization.');
    }

    // Check whether there is already an account for this email.
    try {
      if ($this->accountExistsForEmail($attributes['mail'])) {
        throw new BadRequestHttpException('There already exists an account for the provided email address.');
      }
    }
    catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    return $attributes;
  }

  /**
   * Check whether email used by already existing account.
   *
   * @param string $mail
   * @return bool
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function accountExistsForEmail(string $mail) {
    return !empty($this->entityTypeManager->getStorage('user')
      ->loadByProperties(['mail' => $mail]));
  }

}
