<?php

namespace Drupal\organizations\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\organizations\Access\OrganizationFieldAccess;
use Drupal\organizations\Entity\Organization;
use Drupal\organizations\Event\OrganizationCreateEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\UserStorageInterface;
use Drupal\user_bundle\Entity\TypedUser;
use Drupal\youvo\Exception\FieldAwareHttpException;
use Drupal\youvo\Utility\FieldValidator;
use Drupal\youvo\Utility\RestContentShifter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides Organization Create Resource.
 *
 * @RestResource(
 *   id = "organization:create",
 *   label = @Translation("Organization Create Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/organizations/create/prospect"
 *   }
 * )
 */
class OrganizationCreateResource extends ResourceBase {

  /**
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected Json $serializationJson;

  /**
   * The user storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * The email validator service.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected EmailValidatorInterface $emailValidator;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

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
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    Json $serialization_json,
    UserStorageInterface $user_storage,
    EmailValidatorInterface $email_validator,
    EventDispatcherInterface $event_dispatcher,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->serializationJson = $serialization_json;
    $this->userStorage = $user_storage;
    $this->emailValidator = $email_validator;
    $this->eventDispatcher = $event_dispatcher;
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
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('email.validator'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get(Request $request) {

    // Get email query parameter.
    $email = trim($request->query->get('mail'));

    // Check whether email was provided.
    if (empty($email)) {
      return new ModifiedResourceResponse([
        'message' => 'The email address was not provided.',
        'field' => 'mail',
      ], 400);
    }

    // Check whether email is valid.
    if (!$this->emailValidator->isValid($email)) {
      return new ModifiedResourceResponse([
        'message' => 'The provided email address is not valid.',
        'field' => 'mail',
      ], 400);
    }

    // Check whether there exists an account for the given email.
    if ($this->accountExistsForEmail($email)) {
      return new ModifiedResourceResponse([
        'message' => 'There already exists an account for the provided email address.',
        'field' => 'mail',
      ], 409);
    }

    // There is no account registered for this email - success.
    return new ModifiedResourceResponse();
  }

  /**
   * Responds to POST requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(Request $request) {

    try {
      // Create new organization user.
      $organization = Organization::create(['type' => 'organization']);
      $attributes = $this->validateAndShiftRequest($request);
      $this->populateFields($attributes, $organization);
      $organization->setPassword((new Random)->string(32));
      $organization->enforceIsNew();
      $organization->addRole('prospect');
      $organization->activate();
      $organization->save();

      // Dispatch organization create event.
      $event = new OrganizationCreateEvent($organization, $request);
      $this->eventDispatcher->dispatch($event);

      // @todo langcode.
    }
    catch (FieldAwareHttpException $e) {
      return new ModifiedResourceResponse([
        'message' => $e->getMessage(),
        'field' => $e->getField(),
      ], $e->getStatusCode());
    }
    catch (HttpException $e) {
      return new ModifiedResourceResponse($e->getMessage(), $e->getStatusCode());
    }

    return new ModifiedResourceResponse();
  }

  /**
   * Checks and distills values for organizations.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The shifted organization attributes.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Drupal\youvo\Exception\FieldAwareHttpException
   */
  protected function validateAndShiftRequest(Request $request) {

    // Decode content of the request.
    $content = $this->serializationJson->decode($request->getContent());

    // Decline request body without organization data.
    if (empty($content['data']) ||
      !in_array('organization', array_column($content['data'], 'type'))) {
      throw new HttpException(400, 'Request body does not provide organization data.');
    }

    // Get attributes from request content.
    $attributes = RestContentShifter::shiftAttributesByType($content, 'organization');

    // Check if valid email is provided.
    if (empty($attributes['mail']) ||
      !$this->emailValidator->isValid($attributes['mail'])) {
      throw new FieldAwareHttpException(400,
        'Need to provide valid mail to register organization.',
        'mail');
    }

    // Check whether there is already an account for this email.
    if ($this->accountExistsForEmail($attributes['mail'])) {
      throw new FieldAwareHttpException(409,
        'There already exists an account for the provided email address.',
        'mail');
    }

    return $attributes;
  }

  /**
   * Populate organization fields from request attributes.
   *
   * @param array $attributes
   *   Contains organization attributes.
   * @param \Drupal\user_bundle\Entity\TypedUser $organization
   *   The organization.
   */
  protected function populateFields(array $attributes, TypedUser $organization) {

    // Prepare, validate and set each field.
    foreach ($attributes as $field_key => $value) {
      $field_name = $this->validateAndRenameField($field_key, $organization);
      $field_definition = $organization->getFieldDefinition($field_name);
      $this->checkFieldAccess($field_definition, $field_key);
      $this->validateFieldValue($field_definition, $field_key, $value);
      $organization->set($field_name, $value);

      // Set username identical to provided mail.
      if ($field_name == 'mail') {
        $organization->setUsername($value);
      }
    }
  }

  /**
   * Checks whether email used by already existing account.
   */
  protected function accountExistsForEmail(string $email) {
    return !empty($this->userStorage->loadByProperties(['mail' => $email]));
  }

  /**
   * Resolves the field name.
   */
  protected function validateAndRenameField(string $field_key, TypedUser $organization) {
    // Validate if field is available. Target field_name instead of name.
    if ($organization->hasField($field_key) && $field_key != 'name') {
      $field_name = $field_key;
    }
    else {
      $field_name = 'field_' . $field_key;
      if (!$organization->hasField($field_name)) {
        throw new FieldAwareHttpException(400,
          'Malformed request body. Organizations do not provide the field ' . $field_key,
          $field_key);
      }
    }
    return $field_name;
  }

  /**
   * Checks the field access with the help of OrganizationFieldAccess.
   */
  protected function checkFieldAccess(FieldDefinitionInterface $field_definition, string $field_key) {
    $fields_allowed = array_merge(['mail', 'field_referral'],
      OrganizationFieldAccess::EDIT_OWNER_OR_MANAGER);
    if (!OrganizationFieldAccess::isFieldOfGroup($field_definition, $fields_allowed)) {
      throw new FieldAwareHttpException(403,
        'Access Denied. Not allowed to set ' . $field_key,
        $field_key);
    }
  }

  /**
   * Validates the field value with the help of the FieldValidator.
   */
  protected function validateFieldValue(FieldDefinitionInterface $field_definition, string $field_key, mixed $value) {
    if (!FieldValidator::validate($field_definition, $value)) {
      throw new FieldAwareHttpException(400,
        'Malformed request body. Unable to validate the organization field ' . $field_key,
        $field_key);
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
      $route->setRequirement('_custom_access', '\Drupal\organizations\Controller\OrganizationAccessController::accessCreate');
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
