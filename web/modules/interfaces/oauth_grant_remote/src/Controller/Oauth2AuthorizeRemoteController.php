<?php

namespace Drupal\oauth_grant_remote\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\SessionManager;
use Drupal\Core\Utility\Error;
use Drupal\user\Entity\User;
use GuzzleHttp\Client;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\simple_oauth\Controller\Oauth2AuthorizeController;
use Drupal\simple_oauth\KnownClientsRepositoryInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extend Oauth2AuthorizeController to authenticate users remotely.
 */
class Oauth2AuthorizeRemoteController extends Oauth2AuthorizeController {

  /**
   * Guzzle http client service.
   *
   * @var \GuzzleHttp\Client
   */
  private $httpClient;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private $request;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * The youvo logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * The session manager.
   *
   * @var \Drupal\Core\Session\SessionManager
   */
  private $sessionManager;

  /**
   * Extend the Oauth2AuthorizeController construct.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $message_factory
   *   The PSR-7 converter.
   * @param \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface $grant_manager
   *   The plugin.manager.oauth2_grant.processor service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\simple_oauth\KnownClientsRepositoryInterface $known_clients_repository
   *   The known client repository service.
   * @param \GuzzleHttp\Client $http_client
   *   Guzzle http client service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   * @param \Drupal\Core\Session\SessionManager $session_manager
   *   The session manager.
   */
  public function __construct(
    HttpMessageFactoryInterface $message_factory,
    Oauth2GrantManagerInterface $grant_manager,
    ConfigFactoryInterface $config_factory,
    KnownClientsRepositoryInterface $known_clients_repository,
    Client $http_client,
    Request $request,
    TimeInterface $time,
    LoggerChannelInterface $logger,
    Connection $database,
    SessionManager $session_manager,
  ) {
    parent::__construct($message_factory, $grant_manager, $config_factory, $known_clients_repository);
    $this->httpClient = $http_client;
    $this->request = $request;
    $this->time = $time;
    $this->logger = $logger;
    $this->database = $database;
    $this->sessionManager = $session_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('psr7.http_message_factory'),
      $container->get('plugin.manager.oauth2_grant.processor'),
      $container->get('config.factory'),
      $container->get('simple_oauth.known_clients'),
      $container->get('http_client'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('datetime.time'),
      $container->get('logger.factory')->get('youvo'),
      $container->get('database'),
      $container->get('session_manager')
    );
  }

  /**
   * Authorizes the code generation or prints the confirmation form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   The response.
   *
   * @throws \Exception
   */
  public function authorize(Request $request) {

    // Check configuration.
    if (empty($this->configFactory->get('oauth_grant_remote.settings')->get('jwt_expiration')) ||
      empty($this->configFactory->get('oauth_grant_remote.settings')->get('jwt_key_path')) ||
      empty($this->configFactory->get('oauth_grant_remote.settings')->get('auth_relay_url'))) {
      $this->logger
        ->error('Auth Relay is not configured. Check the OAuth Grant Remote settings form.');
      return OAuthServerException::serverError('Auth Relay is not configured.')
        ->generateHttpResponse(new Response());
    }

    // Get all cookies registered under the host domain.
    // Note we can not get all the session cookies directly. Therefore, we
    // extract them from all present session cookies.
    $cookies = $this->request->cookies->all();
    $prefix = (Request::createFromGlobals()->isSecure() ? 'SSESS' : 'SESS');
    $session_cookies = array_filter(
      $cookies, fn($c) => str_starts_with($c, $prefix) &&
      strlen(substr($c, strlen($prefix))) == 32, ARRAY_FILTER_USE_KEY
    );

    // Now get the current session cookie and the corresponding user. It can
    // be excluded from the request towards the Auth Relay. Later, we will
    // also cross-check the Uid provided by the current session with the Uid
    // delivered by the Auth Relay.
    $local_session_uid = -1;
    if ($this->request->hasSession()) {
      $local_session = $this->request->getSession();
      $local_session_id = $local_session->getId();
      $local_session_uid = $local_session->get('uid');
      $session_cookies = array_filter($session_cookies,
        fn($c) => $c != $local_session_id);
    }

    // Add a test session here.
    // @todo Delete before release.
    $session_cookies['test'] = 'session';

    // If there are no sessions, the user needs to log in on the original host.
    if (empty($session_cookies)) {
      // @todo Login on original host.
      return OAuthServerException::serverError('Not authenticated on original host.')
        ->generateHttpResponse(new Response());
    }

    // If there is a session or multiple sessions, contact the Auth Relay.
    // Prepare a JWT for the Auth Relay.
    $path = $this->configFactory
      ->get('oauth_grant_remote.settings')
      ->get('jwt_key_path');
    $key_path = 'file://' . $path;
    $key = InMemory::file($key_path);
    $config = Configuration::forSymmetricSigner(new Sha512(), $key);
    $config->setValidationConstraints(new LooseValidAt(new SystemClock(new \DateTimeZone(\date_default_timezone_get()))));

    // Build the JWT.
    $expiry = $this->configFactory
      ->get('oauth_grant_remote.settings')
      ->get('jwt_expiration');
    $builder = $config->builder()
      ->issuedAt(new \DateTimeImmutable('@' . $this->time->getCurrentTime()))
      ->issuedBy($this->request->getHost())
      ->expiresAt(new \DateTimeImmutable('@' . ($this->time->getCurrentTime() + $expiry)))
      ->withClaim('sessions', $session_cookies);
    $jwt = $builder->getToken($config->signer(), $config->signingKey())->toString();

    try {
      // Sending POST Request with the JWT to the Auth Relay.
      $auth_relay_url = $this->configFactory
        ->get('oauth_grant_remote.settings')
        ->get('auth_relay_url');
      $relay = $this->httpClient
        ->post($auth_relay_url, ['json' => ['jwt' => $jwt]]);
    }
    catch (ClientException $e) {
      $variables = Error::decodeException($e);
      $variables['local_uid'] = $local_session_uid;
      $this->logger
        ->error('Unable to contact Auth Relay. Hints: local_uid = %local_uid. %type: @message in %function (line %line of %file).', $variables);
      return OAuthServerException::serverError('Unable to contact Auth Relay.')
        ->generateHttpResponse(new Response());
    }

    // Decode the response and parse the received JWT.
    $relay_response = json_decode($relay->getBody());

    // If the Auth Relay does not deliver a JWT, there was no valid session
    // found on the host site, and we have to log in the user on the original
    // host.
    if (!isset($relay_response->jwt)) {
      // @todo Login on original host.
      return OAuthServerException::serverError('Not authenticated on original host.')
        ->generateHttpResponse(new Response());
    }

    try {
      // Parse JWT.
      $remote_jwt = $config->parser()->parse($relay_response->jwt);
    }
    catch (CannotDecodeContent | InvalidTokenStructure | UnsupportedHeaderFound $e) {
      $variables = Error::decodeException($e);
      $this->logger
        ->error('Unable to decode JWT from Auth Relay. Hints: local_uid = %local_uid. %type: @message in %function (line %line of %file).', $variables);
      return OAuthServerException::serverError('Unable to decode JWT from Auth Relay.')
        ->generateHttpResponse(new Response());
    }

    // Validate JWT message.
    $constraints = $config->validationConstraints();
    if (!$config->validator()->validate($remote_jwt, ...$constraints)) {
      $this->logger
        ->error('Unable to validate JWT from Auth Relay. Hints: local_uid = %local_uid.');
      return OAuthServerException::serverError('Unable to validate JWT from Auth Relay.')
        ->generateHttpResponse(new Response());
    }

    // Get the account delivered by Auth Relay.
    $claims = $remote_jwt->claims()->all();
    $account = $claims['account'];

    // Check if the user is a creative on the host.
    if (!array_key_exists(3, $account['roles'])) {
      $this->logger
        ->error('Only available for creatives.');
      // @todo Add redirect that informs organisations that academy is only for creatives.
      return OAuthServerException::accessDenied('Only available for creatives.')
        ->generateHttpResponse(new Response());
    }

    // Compare local session Uid and relayed account Uid. If both match, we can
    // continue with the parent authorize callback. The currently logged in user
    // is valid and both original and sub host.
    // If the Uids do not match. We log out the user from the current platform,
    // destroy the current session and use the relayed account in the following.
    // @todo This should be moved to the beginning. Therefore, we need to
    //   disable logins on the current page. Then, the sessions never collide.
    if ($local_session_uid > 0) {
      if ($local_session_uid == $account['uid']) {
        return parent::authorize($request);
      }
      else {
        /** @var \Drupal\Core\Session\AccountProxyInterface $account */
        $account = $this->currentUser();
        $this->sessionManager->destroy();
        $account->setAccount(new AnonymousUserSession());
      }
    }

    // See if the relayed user already exists in the database.
    $account_ids = $this->entityTypeManager()
      ->getStorage('user')->getQuery()
      ->condition('uid', $account['uid'])
      ->range(0, 1)
      ->execute();

    // If a user was found, we can log in the user here and continue with the
    // parent authorize callback.
    if (!empty($account_ids)) {
      $uid = reset($account_ids);
      $account = User::load($uid);
    }

    // Otherwise, the user does not exist on the current platform. We will
    // create a shell user and login that new user.
    // Note that we set the name as mail to fix an old regression regarding
    // mail login.
    else {
      $shell_user = User::create([
        'uid' => $account['uid'],
        'name' => $account['mail'],
        'mail' => $account['mail'],
        'created' => $account['created'],
        'access' => $account['access'],
        'login' => $account['login'],
        'init' => $account['init'],
        'roles' => ['creative'],
        'status' => $account['status'],
      ]);

      // Set full name.
      if ($shell_user->hasField('fullname')) {
        $shell_user->set('fullname', $account['fullname']);
      }

      // Save shell user.
      $shell_user->save();

      // Pass on the hash of the password to mitigate later migration issues.
      $this->database->update('users_field_data')
        ->condition('uid', $shell_user->id())
        ->fields(['pass' => $account['pass']])
        ->execute();

      // Reload the complete user object.
      $account = User::load($shell_user->id());
    }

    // Login the shell user and continue with parent authorize callback.
    user_login_finalize($account);
    return parent::authorize($request);
  }

}
