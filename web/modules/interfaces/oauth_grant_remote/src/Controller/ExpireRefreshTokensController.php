<?php

namespace Drupal\oauth_grant_remote\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\SessionManager;
use Drupal\Core\Utility\Error;
use Drupal\rest\ModifiedResourceResponse;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Endpoint to expire refresh tokens.
 */
class ExpireRefreshTokensController extends ControllerBase {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The token storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $tokenStorage;

  /**
   * The session manager.
   *
   * @var \Drupal\Core\Session\SessionManager
   */
  private SessionManager $sessionManager;

  /**
   * ExpireRefreshTokensController constructor.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityStorageInterface $token_storage
   *   The token storage.
   * @param \Drupal\Core\Session\SessionManager $session_manager
   *   The session manager.
   */
  public function __construct(
    LoggerInterface $logger,
    EntityStorageInterface $token_storage,
    SessionManager $session_manager,
  ) {
    $this->logger = $logger;
    $this->tokenStorage = $token_storage;
    $this->sessionManager = $session_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager')->getStorage('oauth2_token'),
      $container->get('session_manager')
    );
  }

  /**
   * Processes POST requests to /oauth/expire.
   */
  public function response(ServerRequestInterface $request) {

    // Check configuration.
    if (empty($this->config('oauth_grant_remote.settings')->get('jwt_key_path'))) {
      $this->logger
        ->error('Remote user logout resource requires key configuration.');
      throw new HttpException(500, 'Internal Server Error');
    }

    // Prepare a JWT for the Remote Logout.
    $path = $this->config('oauth_grant_remote.settings')
      ->get('jwt_key_path');
    $key_path = 'file://' . $path;
    $key = InMemory::file($key_path);
    $config = Configuration::forSymmetricSigner(new Sha512(), $key);
    $config->setValidationConstraints(new LooseValidAt(new SystemClock(new \DateTimeZone(\date_default_timezone_get()))));

    // Get JWT from url parameter.
    $params = $request->getParsedBody();
    $jwt = $params['jwt'];

    // Check if JWT has content.
    if (empty($jwt)) {
      $this->logger
        ->error('No message in remote user logout resource.');
      throw new BadRequestHttpException('Bad Request. No message.');
    }

    try {
      // Parse JWT.
      /** @var \Lcobucci\JWT\Token\Plain $remote_jwt */
      $remote_jwt = $config->parser()->parse($jwt);
    }
    catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('Unable to decode JWT in remote user logout resource. %type: @message in %function (line %line of %file).', $variables);
      throw new BadRequestHttpException('Bad Request. Unable to decode JWT.');
    }

    // Validate JWT message.
    $constraints = $config->validationConstraints();
    if (!$config->validator()->validate($remote_jwt, ...$constraints)) {
      $this->logger
        ->error('Unable to validate JWT in remote user logout resource.');
      throw new BadRequestHttpException('Bad Request. Unable to validate JWT.');
    }

    // Get the claims delivered by Remote Logout.
    $remote_claims = $remote_jwt->claims()->all();

    // Get the account delivered by Remote Logout.
    $remote_account = $remote_claims['account'];

    // Only accept claims issued by main page.
    if (!($remote_claims['iss'] == 'https://youvo.org' ||
      $remote_claims['iss'] == 'https://www.youvo.org')) {
      throw new BadRequestHttpException('Bad Request. Expiry is activated only for production environment.');
    }

    // Nothing to do if this account is not a creative.
    if (!array_key_exists(3, $remote_account['roles'])) {
      return new ModifiedResourceResponse(NULL, 200);
    }

    // If this user is a creative, invalidate refresh tokens.
    $query = $this->tokenStorage->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('auth_user_id', $remote_account['uid']);
    $query->condition('bundle', 'refresh_token');
    $token_ids = $query->execute();
    try {
      $tokens = $token_ids
        ? array_values($this->tokenStorage->loadMultiple(array_values($token_ids)))
        : [];
      $this->tokenStorage->delete($tokens);
    }
    catch (EntityStorageException $e) {
      $this->logger
        ->error('Unable to delete Tokens in remote user logout resource.');
      throw new HttpException(500, 'Internal Server Error', $e);
    }

    // Also, destroy all sessions of user. We do not care about users being
    // logged in from different devices, because if all refresh tokens are
    // invalidated, the user has to authenticate again and consequently will be
    // logged in to the data provider again.
    $this->sessionManager->delete($remote_account['uid']);

    return new ModifiedResourceResponse(NULL, 200);
  }

}
