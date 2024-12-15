<?php

namespace Drupal\creatives\Plugin\rest\resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Component\Utility\Random;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\creatives\Entity\Creative;
use Drupal\creatives\Event\CreativeRegisterEvent;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponseInterface;
use Drupal\youvo\Exception\FieldAwareHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

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
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The email validator.
   */
  protected EmailValidatorInterface $emailValidator;

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->emailValidator = $container->get('email.validator');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->languageManager = $container->get('language_manager');
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * Responds to GET requests.
   */
  public function get(Request $request): ResourceResponseInterface {

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
   */
  public function post(Request $request): ResourceResponseInterface {
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
    catch (HttpException | EntityStorageException $e) {
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
   *   The shifted creative attributes.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   * @throws \Drupal\youvo\Exception\FieldAwareHttpException
   */
  protected function validateAndShiftRequest(Request $request): array {

    // Decode content of the request.
    $content = Json::decode($request->getContent());

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
  protected function accountExistsForEmail(string $email): bool {
    $accounts = $this->entityTypeManager
      ->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    return !empty($accounts);
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
      in_array(FALSE, array_map(static fn($r) => array_key_exists('id', $r), $relationships['skills']['data']), TRUE) ||
      count(array_filter(array_column($relationships['skills']['data'], 'id'),
        [Uuid::class, 'isValid'])) !== count($relationships['skills']['data'])) {
      throw new FieldAwareHttpException(400,
        'Need to provide well-structured skills array to register user.',
        'skills');
    }
    // Can the provided skills be loaded?
    $skills_uuids = array_column($relationships['skills']['data'], 'id');
    $skills_ids = $this->entityTypeManager
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('uuid', $skills_uuids, 'IN')
      ->execute();
    if (count($skills_ids) !== count($skills_uuids)) {
      throw new FieldAwareHttpException(409,
        'Unable to load one or more of the provided skills.',
        'skills');
    }
    return $skills_ids;
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
      $route->setRequirement('_custom_access', '\Drupal\creatives\Access\CreativeEntityAccess::accessCreate');
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
