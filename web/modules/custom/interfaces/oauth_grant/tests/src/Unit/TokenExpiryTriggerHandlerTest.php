<?php

namespace Drupal\Tests\oauth_grant\Unit;

use Drupal\oauth_grant\TokenExpiryTriggerHandler;
use Drupal\simple_oauth\TokenExpiryTriggerHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests the token revocation on user updates.
 */
#[CoversClass(TokenExpiryTriggerHandler::class)]
#[Group('oauth_grant')]
class TokenExpiryTriggerHandlerTest extends UnitTestCase {

  /**
   * Creates a user mock.
   */
  protected function user(string $password, array $roles, bool $blocked): UserInterface&MockObject {
    $user = $this->createMock(UserInterface::class);
    $user->method('getPassword')->willReturn($password);
    $user->method('getRoles')->willReturn($roles);
    $user->method('isBlocked')->willReturn($blocked);
    return $user;
  }

  /**
   * Tests which changes revoke the tokens.
   */
  #[DataProvider('provideChanges')]
  public function testHandleUserUpdate(array $original, array $updated, bool $revoked): void {

    $inner = $this->createMock(TokenExpiryTriggerHandlerInterface::class);
    $inner->expects($revoked ? $this->once() : $this->never())
      ->method('handleUserUpdate');

    $user = $this->user(...$updated);
    $user->method('getOriginal')->willReturn($this->user(...$original));

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
   */
  public function testMissingOriginal(): void {

    $inner = $this->createMock(TokenExpiryTriggerHandlerInterface::class);
    $inner->expects($this->once())->method('handleUserUpdate');

    (new TokenExpiryTriggerHandler($inner))
      ->handleUserUpdate($this->user('hash', [], FALSE));
  }

}
