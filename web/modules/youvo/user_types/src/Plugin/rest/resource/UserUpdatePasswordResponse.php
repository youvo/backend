<?php

namespace Drupal\user_types\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\UserStorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provides User Update Password Resource.
 *
 * @RestResource(
 *   id = "user:update:password",
 *   label = @Translation("User Update Password"),
 *   uri_paths = {
 *     "canonical" = "/api/users/update/password"
 *   }
 * )
 */
class UserUpdatePasswordResponse extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The serialization by Json service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $serializationJson;

  /**
   * User storage handler.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Constructs a OrganizationCreateResource object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Component\Serialization\Json $serialization_json
   *   The serialization by Json service.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   The user storage handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, Json $serialization_json, UserStorageInterface $user_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->serializationJson = $serialization_json;
    $this->userStorage = $user_storage;
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
      $container->get('current_user'),
      $container->get('serialization.json'),
      $container->get('entity_type.manager')->getStorage('user')
    );
  }

  /**
   * Responds POST requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Contains request data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   Response.
   */
  public function patch(Request $request) {

    // Double-check if user is logged in. This should already be caught by the
    // access handler.
    if ($this->currentUser->isAnonymous()) {
      return new ResourceResponse('The user is not logged in.', 400);
    }

    // Decode content of the request.
    $content = $this->serializationJson->decode($request->getContent());

    // Check whether current_password was provided.
    if (empty($content['current_password'])) {
      return new ResourceResponse('The value current_password was not provided.', 400);
    }

    // Check whether current_password was provided.
    if (empty($content['new_password'])) {
      return new ResourceResponse('The value password was not provided.', 400);
    }

    // Load the user object from the account proxy and set existing password.
    /** @var \Drupal\user\UserInterface $account */
    $account = $this->userStorage->load($this->currentUser->id());
    $account->setExistingPassword(trim($content['current_password']));

    // Load the unchanged account object and check whether the current_password
    // is correct.
    /** @var \Drupal\user\UserInterface $account_unchanged */
    $account_unchanged = $this->userStorage->loadUnchanged($account->id());
    if (!$account->checkExistingPassword($account_unchanged)) {
      return new ResourceResponse('The provided current password is incorrect.', 409);
    }

    /**
     * Set new password and save user.
     *
     * Note that all sessions are destroyed and a new session is migrated from
     * the current user. @see \Drupal\user\Entity\User::postSave()
     *
     * Further, we invalidate all access tokens and a new access token should
     * be requested with the current refresh token. @see simple_oauth.module
     * simple_oauth_entity_update() and the respective patch.
     **/
    try {
      $account->setPassword(trim($content['new_password']));
      $account->save();
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    return new ResourceResponse();
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
      $route->setRequirement('_custom_access', '\Drupal\user_types\Controller\Access::updatePassword');
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
