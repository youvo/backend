<?php

namespace Drupal\user_types\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\rest\ResourceResponseInterface;
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
class UserUpdatePasswordResource extends ResourceBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults) {
    $instance = parent::create($container, ...$defaults);
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * Responds to POST requests.
   */
  public function patch(Request $request): ResourceResponseInterface {

    // Double-check if user is logged in. This should already be caught by the
    // access handler.
    if ($this->currentUser->isAnonymous()) {
      return new ResourceResponse('The user is not logged in.', 403);
    }

    // Decode content of the request.
    $content = Json::decode($request->getContent());

    // Check whether current_password was provided.
    if (empty($content['current_password'])) {
      return new ResourceResponse([
        'message' => 'The value current_password was not provided.',
        'field' => 'current_password',
      ], 400);
    }

    // Check whether current_password was provided.
    if (empty($content['new_password'])) {
      return new ResourceResponse([
        'message' => 'The value new_password was not provided.',
        'field' => 'new_password',
      ], 400);
    }

    $user_storage = $this->entityTypeManager->getStorage('user');

    // Load the user object from the account proxy and set existing password.
    /** @var \Drupal\user\UserInterface $account */
    $account = $user_storage->load($this->currentUser->id());
    $account->setExistingPassword(trim($content['current_password']));

    // Load the unchanged account object and check whether the current_password
    // is correct.
    /** @var \Drupal\user\UserInterface $account_unchanged */
    $account_unchanged = $user_storage->loadUnchanged($account->id());
    if (!$account->checkExistingPassword($account_unchanged)) {
      return new ResourceResponse([
        'message' => 'The provided current password is incorrect.',
        'field' => 'current_password',
      ], 409);
    }

    // Sets the new password and saves the user.
    // Note that all sessions are destroyed and a new session is migrated from
    // the current user. See \Drupal\user\Entity\User::postSave().
    // Further, we invalidate all access tokens and a new access token should
    // be requested with the current refresh token. See simple_oauth.module
    // simple_oauth_entity_update() and the respective patch.
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
  public function routes(): RouteCollection {

    // Gather properties.
    $collection = new RouteCollection();
    $definition = $this->getPluginDefinition();
    $canonical_path = $definition['uri_paths']['canonical'];
    $route_name = str_replace(':', '.', $this->pluginId);

    // Add access check and route entity context parameter for each method.
    foreach ($this->availableMethods() as $method) {
      $route = $this->getBaseRoute($canonical_path, $method);
      $route->setRequirement('_custom_access', '\Drupal\user_types\Access\UserTypeAccess::updatePassword');
      $collection->add("$route_name.$method", $route);
    }

    return $collection;
  }

}
