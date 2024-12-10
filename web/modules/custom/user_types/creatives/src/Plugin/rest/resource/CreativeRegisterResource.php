<?php

namespace Drupal\creatives\Plugin\rest\resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Component\Utility\Random;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\creatives\Entity\Creative;
use Drupal\creatives\Event\CreativeRegisterEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\user\UserStorageInterface;
use Drupal\youvo\Exception\FieldAwareHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides Creative Create Resource.
 *
 * @RestResource(
 *   id = "creative:register",
 *   label = @Translation("Creative Register Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/users/register/creative"
 *   }
 * )
 */
class CreativeRegisterResource extends ResourceBase {

  /**
   * Constructs a CreativeRegisterResource object.
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
   * @param \Drupal\Component\Serialization\Json $serializationJson
   *   The serialization by Json service.
   * @param \Drupal\user\UserStorageInterface $userStorage
   *   The user storage.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $emailValidator
   *   The email validator service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\taxonomy\TermStorageInterface $termStorage
   *   The term storage.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected Json $serializationJson,
    protected UserStorageInterface $userStorage,
    protected EmailValidatorInterface $emailValidator,
    protected EventDispatcherInterface $eventDispatcher,
    protected TermStorageInterface $termStorage,
    protected LanguageManagerInterface $languageManager,
    protected TimeInterface $time,
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
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('serialization.json'),
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('email.validator'),
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('language_manager'),
      $container->get('datetime.time')
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
   */
  public function get(Request $request) {

    // Get email query parameter.
    $email = trim($request->query->get('mail'));

    // Check whether email is empty or not valid.
    if (empty($email) || !$this->emailValidator->isValid($email)) {
      return new ModifiedResourceResponse([
        'message' => 'The provided email address is empty or not valid.',
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
      [$attributes, $skills_ids] = $this->validateAndShiftRequest($request);
      $creative = Creative::create([
        'type' => 'user',
        'mail' => $attributes['mail'],
        'name' => $attributes['mail'],
        'field_city' => $attributes['city'],
        'field_name' => $attributes['name'],
        'field_skills' => $skills_ids,
      ]);
      $creative->setPassword((new Random)->string(32));
      $creative->enforceIsNew();
      $creative->addRole('creative');
      $creative->activate();
      $creative->save();

      // Assemble invite link.
      // @todo Adjust langcode.
      // $organization->getPreferredLangcode();
      $langcode = 'de';
      $timestamp = $this->time->getCurrentTime();
      $link = Url::fromRoute('creatives.register',
        [
          'uid' => $creative->id(),
          'timestamp' => $timestamp,
          'hash' => user_pass_rehash($creative, $timestamp),
        ],
        [
          'absolute' => TRUE,
          'language' => $this->languageManager->getLanguage($langcode),
        ]
      )->toString();
      $this->eventDispatcher->dispatch(new CreativeRegisterEvent($creative, $link));
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
   * Checks and distills values for creatives.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return array
   *   The shiftedc creative attributes.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Drupal\youvo\Exception\FieldAwareHttpException
   */
  protected function validateAndShiftRequest(Request $request) {

    // Decode content of the request.
    $content = $this->serializationJson->decode($request->getContent());

    // Decline request body without organization data.
    if (empty($content['data'])) {
      throw new HttpException(400, 'Request body does not provide user data.');
    }

    // Get attributes and relationships from request content.
    $attributes = $content['data']['attributes'] ?? [];
    $relationships = $content['data']['relationships'] ?? [];

    // Validate email, name and skills.
    $this->validateEmailFromAttributes($attributes);
    $this->validateNameFromAttributes($attributes);
    $this->validateCityFromAttributes($attributes);
    $skills_ids = $this->validateSkillsFromRelationships($relationships);

    return [$attributes, $skills_ids];
  }

  /**
   * Validates provided email address.
   */
  protected function validateEmailFromAttributes(array $attributes): void {
    if (empty($attributes['mail']) ||
      !$this->emailValidator->isValid($attributes['mail'])) {
      throw new FieldAwareHttpException(400,
        'Need to provide valid email to register user.',
        'mail');
    }
    if ($this->accountExistsForEmail($attributes['mail'])) {
      throw new FieldAwareHttpException(409,
        'There already exists an account for the provided email address.',
        'mail');
    }
  }

  /**
   * Checks whether email used by already existing account.
   */
  protected function accountExistsForEmail(string $email) {
    return !empty($this->userStorage->loadByProperties(['mail' => $email]));
  }

  /**
   * Validates name attribute.
   */
  protected function validateNameFromAttributes(array $attributes): void {
    if (empty($attributes['name']) ||
      !is_string($attributes['name']) ||
      strlen($attributes['name']) >= 255) {
      throw new FieldAwareHttpException(400,
        'Need to provide valid name to register user.',
        'name');
    }
  }

  /**
   * Validates city attribute.
   */
  protected function validateCityFromAttributes(array $attributes): void {
    if (array_key_exists('city', $attributes) &&
      (!(is_string($attributes['city']) || is_null($attributes['city'])) ||
      strlen($attributes['city']) >= 255)) {
      throw new FieldAwareHttpException(400,
        'Provided city is not in a valid format.',
        'city');
    }
  }

  /**
   * Validates provided skills.
   */
  protected function validateSkillsFromRelationships(array $relationships): array {
    // Is it empty?
    // Does every entry provide an ID?
    // Is the ID a valid UUID?
    if (empty($relationships['skills']['data']) ||
      in_array(FALSE, array_map(fn($r) => array_key_exists('id', $r), $relationships['skills']['data'])) ||
      count(array_filter(array_column($relationships['skills']['data'], 'id'),
        [Uuid::class, 'isValid'])) != count($relationships['skills']['data'])) {
      throw new FieldAwareHttpException(400,
        'Need to provide well-structured skills array to register user.',
        'skills');
    }
    // Can the provided skills be loaded?
    $skills_uuids = array_column($relationships['skills']['data'], 'id');
    $skills_ids = $this->termStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uuid', $skills_uuids, 'IN')
      ->execute();
    if (count($skills_ids) != count($skills_uuids)) {
      throw new FieldAwareHttpException(409,
        'Unable to load one or more of the provided skills.',
        'skills');
    }
    return $skills_ids;
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
      $route->setRequirement('_custom_access', '\Drupal\creatives\Controller\CreativeAccessController::accessCreate');
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
