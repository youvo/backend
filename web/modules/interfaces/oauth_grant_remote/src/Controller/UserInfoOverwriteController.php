<?php

namespace Drupal\oauth_grant_remote\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
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
   * The authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user;

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  private $serializer;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * UserInfo constructor.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   The user.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  private function __construct(AccountProxyInterface $user, SerializerInterface $serializer, ConfigFactoryInterface $config_factory) {
    $this->user = $user->getAccount();
    $this->serializer = $serializer;
    $this->config = $config_factory
      ->get('simple_oauth.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('serializer'),
      $container->get('config.factory')
    );
  }

  /**
   * The controller.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   *
   * @throws \Symfony\Component\Serializer\Exception\ExceptionInterface
   */
  public function handle() {
    if (!$this->user instanceof TokenAuthUser) {
      throw new AccessDeniedHttpException('This route is only available for authenticated requests using OAuth2.');
    }
    assert($this->serializer instanceof NormalizerInterface);
    $identifier = $this->user->id();
    $user_entity = new UserEntityWithClaims();
    $user_entity->setIdentifier($identifier);
    $data = $this->serializer
      ->normalize($user_entity, 'json', [$identifier => $this->user]);
    $data['profile'] = 'https://www.youvo.org/kreative/' . $identifier;
    if ($this->user->hasField('fullname')) {
      $data['name'] = $this->user->get('fullname')->value;
    }
    $data['preferred_username'] = $data['name'];
    $data['uuid'] = $this->user->uuid();
    return JsonResponse::create($data);
  }

}
