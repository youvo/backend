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
   * Tests the exemption per permissions and path.
   *
   * Staff roles (manager, supervisor, observer) hold 'access site', so they
   * reach the manager pane, the logbook and the dashboard, but no /user path.
   * Only administrators hold 'bypass blocker mode'.
   */
  #[DataProvider('exemptProvider')]
  public function testExempt(array $permissions, string $path, bool $expected): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      fn (string $permission) => in_array($permission, $permissions, TRUE)
    );
    $route_match = new RouteMatch('test', new Route($path));

    $blocker_mode = new BlockerMode($this->createMock(StateInterface::class));
    $this->assertSame($expected, $blocker_mode->exempt($route_match, $account));
  }

  /**
   * Provides permissions, a path and the exemption.
   */
  public static function exemptProvider(): array {
    $pane = '/project/manage/context/{project}';
    $staff = ['access site'];
    $admin = ['access site', 'bypass blocker mode'];
    return [
      'staff on the manager pane' => [$staff, $pane, TRUE],
      'staff on the logbook' => [$staff, '/logbook', TRUE],
      'staff on a user path' => [$staff, '/user/{user}', FALSE],
      'organization on the manager pane' => [[], $pane, FALSE],
      'administrator on a user path' => [$admin, '/user/{user}', TRUE],
    ];
  }

}
