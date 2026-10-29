<?php

declare(strict_types=1);

namespace Drupal\Tests\oauth_grant_remote\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oauth_grant_remote\Controller\Oauth2AuthorizeRemoteController;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that the authorize controller reads the Auth Relay response.
 *
 * @group oauth_grant_remote
 */
class AuthRelayResponseTest extends KernelTestBase {

  /**
   * The Auth Relay URL.
   */
  protected const string RELAY_URL = 'https://relay.example.com';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'file',
    'image',
    'oauth_grant_remote',
    'options',
    'path_alias',
    'serialization',
    'simple_oauth',
    'system',
    'user',
  ];

  /**
   * The JWT configuration shared with the mocked Auth Relay.
   */
  protected Configuration $jwtConfig;

  /**
   * The path to the JWT key file.
   */
  protected string $keyPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('path_alias');

    $key = random_bytes(64);
    // The controller reads the key from a file:// path, so use a real file.
    $this->keyPath = tempnam(sys_get_temp_dir(), 'relay');
    file_put_contents($this->keyPath, $key);
    $this->jwtConfig = Configuration::forSymmetricSigner(new Sha512(), InMemory::plainText($key));

    $this->config('oauth_grant_remote.settings')
      ->set('development', 0)
      ->set('jwt_expiration', 60)
      ->set('jwt_key_path', $this->keyPath)
      ->set('auth_relay_url', self::RELAY_URL)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    unlink($this->keyPath);
    parent::tearDown();
  }

  /**
   * Tests that a relayed organization is sent back to the Auth Relay.
   */
  public function testRelayedOrganizationIsRedirected(): void {
    $this->mockAuthRelay(['uid' => 5, 'roles' => [2 => 'authenticated user', 4 => 'organisation']]);

    $request = Request::create('/oauth/authorize', 'GET', ['client_id' => 'client']);
    $request->cookies->set('SESS' . str_repeat('a', 32), 'session-id');

    $response = Oauth2AuthorizeRemoteController::create($this->container)->authorize($request);

    $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
    $this->assertSame(self::RELAY_URL . '?r=oa', $response->getTargetUrl());
  }

  /**
   * Replaces the HTTP client with a mocked Auth Relay.
   *
   * The mock answers like the Drupal 7 relay: a JSON body with a JWT that
   * echoes the state and carries the given account.
   */
  protected function mockAuthRelay(array $account): void {
    $handler = new MockHandler([
      function (RequestInterface $request) use ($account) {
        $body = Json::decode((string) $request->getBody());
        $token = $this->jwtConfig->parser()->parse($body['jwt']);
        $this->assertInstanceOf(UnencryptedToken::class, $token);
        $state = $token->claims()->get('state');
        $now = new \DateTimeImmutable();
        $jwt = $this->jwtConfig->builder()
          ->issuedAt($now)
          ->expiresAt($now->modify('+60 seconds'))
          ->withClaim('state', $state)
          ->withClaim('account', $account)
          ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey())
          ->toString();
        return new Response(200, ['Content-Type' => 'application/json'], Json::encode(['jwt' => $jwt]));
      },
    ]);
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create($handler)]));
  }

}
