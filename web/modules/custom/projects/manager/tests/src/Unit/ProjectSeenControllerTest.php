<?php

namespace Drupal\Tests\manager\Unit;

use Drupal\manager\Controller\ProjectSeenController;
use Drupal\manager\ProjectSeenTracker;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests where the routes that confirm projects redirect to.
 */
#[CoversClass(ProjectSeenController::class)]
#[Group('manager')]
class ProjectSeenControllerTest extends UnitTestCase {

  /**
   * Redirects back for a request with a destination.
   */
  protected function back(?string $destination): RedirectResponse {
    $controller = new class($this->createMock(ProjectSeenTracker::class)) extends ProjectSeenController {

      /**
       * Exposes the redirect.
       */
      public function redirectBack(Request $request): RedirectResponse {
        return $this->back($request);
      }

    };
    $request = Request::create('/project/manage/seen/1', 'POST');
    if ($destination !== NULL) {
      $request->query->set('destination', $destination);
    }
    return $controller->redirectBack($request);
  }

  /**
   * A local destination is followed, any other is replaced by the dashboard.
   */
  #[DataProvider('destinationProvider')]
  public function testBack(?string $destination, string $expected): void {
    $this->assertSame($expected, $this->back($destination)->getTargetUrl());
  }

  /**
   * Destinations and where they lead.
   */
  public static function destinationProvider(): array {
    return [
      'local path' => ['/admin/dashboard', '/admin/dashboard'],
      'local path with query' => ['/projects/manage?page=2', '/projects/manage?page=2'],
      'none' => [NULL, '/admin/dashboard'],
      'protocol relative' => ['//evil.example/path', '/admin/dashboard'],
      'full url' => ['https://evil.example/', '/admin/dashboard'],
      'no slash' => ['evil.example', '/admin/dashboard'],
      'backslash' => ["/\\evil.example", '/admin/dashboard'],
      'line break' => ["/ok\r\nSet-Cookie: x=1", '/admin/dashboard'],
    ];
  }

}
