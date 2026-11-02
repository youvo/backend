<?php

namespace Drupal\Tests\creatives\ExistingSite;

use Drupal\creatives\Plugin\rest\resource\CreativeRegisterResource;
use Drupal\Tests\youvo\Traits\RequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the flood control of the registration endpoint.
 */
#[CoversClass(CreativeRegisterResource::class)]
#[Group('creatives')]
class CreativeRegisterFloodTest extends ExistingSiteBase {

  use RequestTrait;

  /**
   * The flood event of the endpoint.
   */
  protected const string EVENT = 'creatives.register';

  /**
   * The IP address of the test requests.
   */
  protected const string IP = '127.0.0.1';

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->container->get('flood')->clear(static::EVENT, static::IP);
    parent::tearDown();
  }

  /**
   * Tests that the sixth registration within an hour is refused.
   */
  public function testSixthRequestIsRefused(): void {

    $flood = $this->container->get('flood');
    for ($i = 0; $i < 5; $i++) {
      $flood->register(static::EVENT, 3600, static::IP);
    }
    $users = $this->countUsers();

    $request = Request::create('/api/users/register/creative', 'POST', [], [], [], ['REMOTE_ADDR' => static::IP], '{}');
    $request->headers->set('Content-Type', 'application/json');
    $response = $this->doRequest($request);

    $this->assertSame(429, $response->getStatusCode());
    $this->assertSame($users, $this->countUsers());
  }

  /**
   * Tests that every request counts, also a refused one.
   *
   * The request is counted before any work, so parallel requests cannot all
   * pass the check before the first one is counted.
   */
  public function testEveryRequestCounts(): void {
    for ($i = 0; $i < 5; $i++) {
      $this->assertSame(400, $this->post()->getStatusCode());
    }
    $this->assertSame(429, $this->post()->getStatusCode());
  }

  /**
   * Posts an empty body from the test IP address.
   */
  protected function post(): Response {
    $request = Request::create('/api/users/register/creative', 'POST', [], [], [], ['REMOTE_ADDR' => static::IP], '{}');
    $request->headers->set('Content-Type', 'application/json');
    return $this->doRequest($request);
  }

  /**
   * Counts the user accounts.
   */
  protected function countUsers(): int {
    return (int) $this->container->get('entity_type.manager')->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
