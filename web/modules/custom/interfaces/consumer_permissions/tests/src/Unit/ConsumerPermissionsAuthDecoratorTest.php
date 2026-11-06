<?php

namespace Drupal\Tests\consumer_permissions\Unit;

use Drupal\consumer_permissions\ConsumerPermissionsAuthDecorator;
use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the password login during an OAuth authorization.
 */
#[CoversClass(ConsumerPermissionsAuthDecorator::class)]
#[Group('consumer_permissions')]
class ConsumerPermissionsAuthDecoratorTest extends UnitTestCase {

  const string UUID = 'ff5a1e31-557c-4c17-a7f5-a4a342d3cc19';

  /**
   * Authenticates an account with the given permissions and client ID.
   */
  protected function authenticate(array $permissions, string $client_id): bool {

    $client = $this->createMock(Consumer::class);
    $client->method('id')->willReturn('3');
    $client->method('uuid')->willReturn(static::UUID);
    $consumer_storage = $this->createMock(EntityStorageInterface::class);
    $consumer_storage->method('loadByProperties')
      ->willReturnCallback(fn (array $values) => $values === ['client_id' => 'hub'] ? [3 => $client] : []);

    $account = $this->createMock(UserInterface::class);
    $account->method('hasPermission')
      ->willReturnCallback(fn (string $permission) => in_array($permission, $permissions, TRUE));
    $user_storage = $this->createMock(EntityStorageInterface::class);
    $user_storage->method('loadByProperties')->willReturn([1 => $account]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->willReturnMap([['consumer', $consumer_storage], ['user', $user_storage]]);

    $request_stack = new RequestStack();
    $request_stack->push(new Request(['destination' => '/oauth/authorize?client_id=' . $client_id]));

    $inner = $this->createMock(UserAuthInterface::class);
    $inner->method('authenticate')->willReturn(TRUE);

    return (new ConsumerPermissionsAuthDecorator($entity_type_manager, $request_stack, $inner))
      ->authenticate('tanja@example.org', 'secret');
  }

  /**
   * Tests who may log in on the way to a client.
   */
  public function testAuthenticate(): void {
    $this->assertTrue($this->authenticate(['authorize with client ' . static::UUID], 'hub'));
    $this->assertFalse($this->authenticate(['authorize with client 3'], 'hub'), 'The ID permission no longer grants access.');
    $this->assertFalse($this->authenticate([], 'hub'), 'A missing permission is forbidden.');
    $this->assertFalse($this->authenticate(['authorize with client ' . static::UUID], 'unknown'), 'An unknown client is forbidden.');
  }

}
