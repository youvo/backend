<?php

namespace Drupal\Tests\consumer_permissions\Unit;

use Drupal\consumer_permissions\Controller\ConsumerPermissionsAccess;
use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the access check on the OAuth authorize route.
 */
#[CoversClass(ConsumerPermissionsAccess::class)]
#[Group('consumer_permissions')]
class ConsumerPermissionsAccessTest extends UnitTestCase {

  const string UUID = 'ff5a1e31-557c-4c17-a7f5-a4a342d3cc19';

  /**
   * Checks access for an account with the given permissions and client ID.
   */
  protected function access(bool $authenticated, array $permissions, string $client_id): bool {

    $client = $this->createMock(Consumer::class);
    $client->method('id')->willReturn('3');
    $client->method('uuid')->willReturn(static::UUID);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')
      ->willReturnCallback(fn (array $values) => $values === ['client_id' => 'hub'] ? [3 => $client] : []);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('consumer')->willReturn($storage);

    $request_stack = new RequestStack();
    $request_stack->push(new Request(['client_id' => $client_id]));

    $user = $this->createMock(AccountInterface::class);
    $user->method('hasPermission')
      ->willReturnCallback(fn (string $permission) => in_array($permission, $permissions, TRUE));
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturn($authenticated);
    $account->method('getAccount')->willReturn($user);

    return (new ConsumerPermissionsAccess($entity_type_manager, $request_stack))
      ->accessClient($account)
      ->isAllowed();
  }

  /**
   * Tests who may authorize with a client.
   */
  public function testAccessClient(): void {
    $this->assertTrue($this->access(TRUE, ['authorize with client ' . static::UUID], 'hub'));
    $this->assertFalse($this->access(TRUE, ['authorize with client 3'], 'hub'), 'The ID permission no longer grants access.');
    $this->assertFalse($this->access(TRUE, [], 'hub'), 'A missing permission is forbidden.');
    $this->assertFalse($this->access(TRUE, ['authorize with client ' . static::UUID], 'unknown'), 'An unknown client is forbidden.');
    $this->assertTrue($this->access(FALSE, [], 'unknown'), 'Anonymous users log in first.');
  }

}
