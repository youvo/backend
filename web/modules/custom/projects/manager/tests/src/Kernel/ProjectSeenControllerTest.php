<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\manager\Controller\ProjectSeenController;
use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the routes that confirm projects.
 *
 * @group manager
 */
class ProjectSeenControllerTest extends ProjectSeenKernelTestBase {

  /**
   * Gets the controller.
   */
  protected function controller(): ProjectSeenController {
    return ProjectSeenController::create($this->container);
  }

  /**
   * Counts the rows of confirmations.
   */
  protected function rows(): int {
    return (int) $this->container->get('database')->select('manager_project_seen')->countQuery()->execute()->fetchField();
  }

  /**
   * The routes are POST only, need a token and the manager permission.
   */
  public function testRouteDefinitions(): void {
    $provider = $this->container->get('router.route_provider');
    foreach (['manager.project_seen', 'manager.project_seen_all'] as $name) {
      $route = $provider->getRouteByName($name);
      $this->assertSame(['POST'], $route->getMethods(), $name);
      $this->assertSame('TRUE', $route->getRequirement('_csrf_token'), $name);
      $this->assertSame('general manager access', $route->getRequirement('_permission'), $name);
    }
  }

  /**
   * Only the manager of the organization may confirm a project.
   */
  public function testAccessOnlyForTheManager(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $other_manager = $this->managerOf($this->createMailableProject(ProjectState::Open));
    $controller = $this->controller();
    $this->assertTrue($controller->access($manager, $project)->isAllowed());
    $this->assertFalse($controller->access($other_manager, $project)->isAllowed());
  }

  /**
   * Confirming a project writes the row and redirects to the local path.
   */
  public function testSeenRedirectsToLocalDestination(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->setCurrentUser($manager);
    $request = Request::create('/project/manage/seen/1', 'POST');
    $request->query->set('destination', '/admin/dashboard');
    $response = $this->controller()->seen($request, $project);
    $this->assertSame('/admin/dashboard', $response->getTargetUrl());
    $this->assertSame(1, $this->rows());
  }

  /**
   * A destination that leaves the site is replaced.
   *
   * @dataProvider providerForgedDestinations
   */
  public function testForgedDestinationStaysOnTheSite(string $destination): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $this->setCurrentUser($this->managerOf($project));
    $request = Request::create('/project/manage/seen/1', 'POST');
    $request->query->set('destination', $destination);
    $response = $this->controller()->seen($request, $project);
    $this->assertSame('/admin/dashboard', $response->getTargetUrl());
  }

  /**
   * Destinations that must not be followed.
   */
  public static function providerForgedDestinations(): array {
    return [
      'protocol relative' => ['//evil.example/path'],
      'full url' => ['https://evil.example/'],
      'no slash' => ['evil.example'],
      'backslash' => ["/\\evil.example"],
      'line break' => ["/ok\r\nSet-Cookie: x=1"],
    ];
  }

  /**
   * All projects can be confirmed at once, and only the own ones.
   */
  public function testSeenAll(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $this->createMailableProject(ProjectState::Open);
    $this->setCurrentUser($this->managerOf($project));
    $this->controller()->seenAll(Request::create('/project/manage/seen', 'POST'));
    $this->assertSame(1, $this->rows());
  }

}
