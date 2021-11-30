<?php

namespace Drupal\oauth_grant_remote\Controller;

use Drupal\Component\Datetime\TimeInterface;
use GuzzleHttp\Client;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\simple_oauth\Controller\Oauth2AuthorizeController;
use Drupal\simple_oauth\KnownClientsRepositoryInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
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
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private $time;

  /**
   * Oauth2AuthorizeController construct.
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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    HttpMessageFactoryInterface $message_factory,
    Oauth2GrantManagerInterface $grant_manager,
    ConfigFactoryInterface $config_factory,
    KnownClientsRepositoryInterface $known_clients_repository,
    Client $http_client,
    Request $request,
    TimeInterface $time,
  ) {
    parent::__construct($message_factory, $grant_manager, $config_factory, $known_clients_repository);
    $this->httpClient = $http_client;
    $this->request = $request;
    $this->time = $time;
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
      $container->get('datetime.time')
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
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function authorize(Request $request) {

    // @todo Authenticate user remotely.
    $test = 1;

    return parent::authorize($request);
  }

}
