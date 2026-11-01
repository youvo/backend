<?php

namespace Drupal\Tests\blocker_mode\Unit;

use Drupal\blocker_mode\BlockerMode;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Routing\Route;

/**
 * Tests which accounts and paths are exempt from blocker mode.
 */
#[CoversMethod(BlockerMode::class, 'exempt')]
#[Group('blocker_mode')]
class BlockerModeTest extends UnitTestCase {

  /**
   * Tests the exemption per account and path.
   *
   * Staff roles (manager, supervisor, observer) hold 'access site', so they
   * reach the manager pane, the logbook and the dashboard, but no /user path.
   */
  #[DataProvider('exemptProvider')]
  public function testExempt(array $roles, bool $access_site, string $path, bool $expected): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('getRoles')->willReturn($roles);
    $account->method('hasPermission')->willReturnCallback(
      fn (string $permission) => $access_site && $permission === 'access site'
    );
    $route_match = new RouteMatch('test', new Route($path));

    $blocker_mode = new BlockerMode($this->createMock(StateInterface::class));
    $this->assertSame($expected, $blocker_mode->exempt($route_match, $account));
  }

  /**
   * Provides roles, the access site permission, a path and the exemption.
   */
  public static function exemptProvider(): array {
    $pane = '/project/manage/context/{project}';
    return [
      'manager on the manager pane' => [['authenticated', 'manager'], TRUE, $pane, TRUE],
      'manager on the logbook' => [['authenticated', 'manager'], TRUE, '/logbook', TRUE],
      'manager on a user path' => [['authenticated', 'manager'], TRUE, '/user/{user}', FALSE],
      'organization on the manager pane' => [['authenticated', 'organization'], FALSE, $pane, FALSE],
      'administrator on a user path' => [['authenticated', 'administrator'], FALSE, '/user/{user}', TRUE],
    ];
  }

}
