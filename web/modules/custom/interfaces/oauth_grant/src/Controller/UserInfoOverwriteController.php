<?php

namespace Drupal\oauth_grant\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUser;
use Drupal\simple_oauth\Entities\UserEntityWithClaims;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Controller for the User Info endpoint.
 */
class UserInfoOverwriteController implements ContainerInjectionInterface {

  /**
   * Constructs a new UserInfoOverwriteController object.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected SerializerInterface $serializer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('current_user'),
      $container->get('serializer')
    );
  }

  /**
   * Handles the controller callback.
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  public function handle(): JsonResponse {

    $user = $this->currentUser->getAccount();
    if (!$user instanceof TokenAuthUser) {
      throw new AccessDeniedHttpException('This route is only available for authenticated requests using OAuth2.');
    }

    assert($this->serializer instanceof NormalizerInterface);
    $identifier = $user->id();
    $user_entity = new UserEntityWithClaims();
    $user_entity->setIdentifier($identifier);
    $data = $this->serializer->normalize($user_entity, 'json', [$identifier => $user]);

    if (isset($data['email'])) {
      $data['mail'] = $data['email'];
      unset($data['email']);
    }

    if (isset($data['email_verified'])) {
      $data['mail_verified'] = $data['email_verified'];
      unset($data['email_verified']);
    }

    $data['profile'] = 'https://www.youvo.org/kreative/' . $identifier;
    if ($user->hasField('field_name')) {
      $data['name'] = $user->get('field_name')->value;
    }
    $data['preferred_username'] = $data['name'];
    $data['uuid'] = $user->uuid();
    $data['roles'] = array_column($user->get('roles')->getValue(), 'target_id');

    return new JsonResponse($data);
  }

}
