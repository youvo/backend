<?php

namespace Drupal\Tests\oauth_grant\Unit;

use Drupal\oauth_grant\TokenExpiryTriggerHandler;
use Drupal\simple_oauth\TokenExpiryTriggerHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;

/**
 * Tests the token revocation on user updates.
 *
 * @coversDefaultClass \Drupal\oauth_grant\TokenExpiryTriggerHandler
 * @group oauth_grant
 */
class TokenExpiryTriggerHandlerTest extends UnitTestCase {

  /**
   * Creates a user mock.
   */
  protected function user(string $password, array $roles, bool $blocked): UserInterface {
    $user = $this->createMock(UserInterface::class);
    $user->method('getPassword')->willReturn($password);
    $user->method('getRoles')->willReturn($roles);
    $user->method('isBlocked')->willReturn($blocked);
    return $user;
  }

  /**
   * Tests which changes revoke the tokens.
   *
   * @covers ::handleUserUpdate
   * @dataProvider provideChanges
   */
  public function testHandleUserUpdate(array $original, array $updated, bool $revoked): void {
    $inner = $this->createMock(TokenExpiryTriggerHandlerInterface::class);
    $inner->expects($revoked ? $this->once() : $this->never())
      ->method('handleUserUpdate');

    $user = $this->user(...$updated);
    $user->original = $this->user(...$original);

    (new TokenExpiryTriggerHandler($inner))->handleUserUpdate($user);
  }

  /**
   * Data provider for the user changes.
   */
  public static function provideChanges(): array {
    $base = ['hash', ['authenticated', 'creative'], FALSE];
    return [
      'profile update' => [$base, $base, FALSE],
      'role order only' => [$base, ['hash', ['creative', 'authenticated'], FALSE], FALSE],
      'password' => [$base, ['other', ['authenticated', 'creative'], FALSE], TRUE],
      'roles' => [$base, ['hash', ['authenticated', 'creative', 'manager'], FALSE], TRUE],
      'status' => [$base, ['hash', ['authenticated', 'creative'], TRUE], TRUE],
    ];
  }

  /**
   * Tests that a missing original user revokes the tokens.
   *
   * @covers ::handleUserUpdate
   */
  public function testMissingOriginal(): void {
    $inner = $this->createMock(TokenExpiryTriggerHandlerInterface::class);
    $inner->expects($this->once())->method('handleUserUpdate');

    (new TokenExpiryTriggerHandler($inner))
      ->handleUserUpdate($this->user('hash', [], FALSE));
  }

}
