<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\manager\Controller\ProjectEventsController;
use Drupal\projects\ProjectState;

/**
 * Tests the route that lists all events of a project.
 *
 * @group manager
 */
class ProjectEventsControllerTest extends ProjectSeenKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * Gets the controller.
   */
  protected function controller(): ProjectEventsController {
    return ProjectEventsController::create($this->container);
  }

  /**
   * The route only reads: GET, for managers of the project.
   */
  public function testRouteDefinition(): void {
    $route = $this->container->get('router.route_provider')->getRouteByName('manager.project_events');
    $this->assertSame(['GET'], $route->getMethods());
    $this->assertSame('general manager access', $route->getRequirement('_permission'));
    $this->assertNotNull($route->getRequirement('_custom_access'));
    $this->assertNull($route->getRequirement('_csrf_token'));
  }

  /**
   * Only the manager of the organization may see the events.
   */
  public function testAccessOnlyForTheManager(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $other = $this->managerOf($this->createMailableProject(ProjectState::Open));
    $this->assertTrue($this->controller()->access($manager, $project)->isAllowed());
    $this->assertFalse($this->controller()->access($other, $project)->isAllowed());
  }

  /**
   * The response lists the events and marks the new ones.
   */
  public function testListsTheEvents(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->container->get('manager.project_seen')->markSeen($manager, $project);
    $this->createProjectLog($project, 'project_publish', $manager);
    $this->createProjectLog($project);
    $this->setCurrentUser($manager);
    $response = $this->controller()->events($project);
    $html = $response->getContent();
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('manager-projects__events', $html);
    $this->assertSame(2, substr_count($html, '<li class="manager-projects__event'), 'Two events.');
    $this->assertSame(1, substr_count($html, 'is-new'), 'Only the log of someone else is new.');
    $this->assertStringContainsString('Publish', $html);
    $this->assertStringContainsString('Apply', $html);
    $this->assertStringNotContainsString('events-note', $html);
  }

  /**
   * Over the cap the response says how many there are.
   */
  public function testNotesTheCap(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    for ($i = 0; $i < 102; $i++) {
      $this->createProjectLog($project);
    }
    $this->setCurrentUser($manager);
    $html = $this->controller()->events($project)->getContent();
    $this->assertSame(100, substr_count($html, '<li class="manager-projects__event'));
    $this->assertStringContainsString('events-note', $html);
    $this->assertStringContainsString('102', $html);
  }

  /**
   * The response is not cached: it is the list of one user.
   */
  public function testResponseIsPrivate(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $this->setCurrentUser($this->managerOf($project));
    $response = $this->controller()->events($project);
    $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
  }

}
