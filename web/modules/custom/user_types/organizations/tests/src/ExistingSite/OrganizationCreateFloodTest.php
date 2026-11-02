<?php

namespace Drupal\Tests\organizations\ExistingSite;

use Drupal\Tests\youvo\Traits\RequestTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the flood control of the proposal endpoint.
 */
#[Group('organizations')]
class OrganizationCreateFloodTest extends ExistingSiteBase {

  use RequestTrait;

  /**
   * The flood event of the endpoint.
   */
  protected const string EVENT = 'organizations.create_prospect';

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
   * Tests that every request counts and the sixth one is refused.
   *
   * The request is counted before any work, so parallel requests cannot all
   * pass the check before the first one is counted. A refused request creates
   * no account.
   */
  public function testSixthRequestIsRefused(): void {

    for ($i = 0; $i < 5; $i++) {
      $this->assertSame(400, $this->post()->getStatusCode());
    }
    $users = $this->countUsers();

    $this->assertSame(429, $this->post()->getStatusCode());
    $this->assertSame($users, $this->countUsers());
  }

  /**
   * Posts an empty body from the test IP address.
   */
  protected function post(): Response {
    $request = Request::create('/api/organizations/create/prospect', 'POST', [], [], [], ['REMOTE_ADDR' => static::IP], '{}');
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
