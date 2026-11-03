<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\RequestTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the route that lists all events of a project, through the HTTP kernel.
 *
 * The requests run as the current user of the test process. The site's
 * blocker mode answers every exception of a non-API path with a 403, a wrong
 * method as well.
 */
#[Group('manager')]
class ProjectEventsRouteTest extends ManagerExistingSiteTestBase {

  use RequestTrait;

  /**
   * Requests the events of a project as an account.
   */
  protected function events(AccountInterface $account, ProjectInterface $project, string $method = 'GET'): Response {
    $this->actAs($account);
    return $this->doRequest(Request::create('/project/manage/events/' . $project->id(), $method));
  }

  /**
   * The response lists the events, marks the new ones and is not cached.
   */
  public function testListsTheEvents(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->container->get('manager.project_seen')->markSeen($manager, $project);
    $this->createLog($project, 'project_publish', $manager);
    $this->createLog($project);
    $response = $this->events($manager, $project);
    $html = (string) $response->getContent();
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(2, substr_count($html, '<li class="manager-projects__event'), 'Two events.');
    $this->assertSame(1, substr_count($html, 'is-new'), 'Only the log of someone else is new.');
    $this->assertStringContainsString('Publish', $html);
    $this->assertStringContainsString('Apply', $html);
    $this->assertStringNotContainsString('events-note', $html);
    // The list is the list of one user: nothing may keep it.
    $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
  }

  /**
   * Over the cap the response says how many there are.
   */
  public function testNotesTheCap(): void {
    $project = $this->createProject(ProjectState::Open);
    for ($i = 0; $i < 102; $i++) {
      $this->createLog($project);
    }
    $html = (string) $this->events($this->managerOf($project), $project)->getContent();
    $this->assertSame(100, substr_count($html, '<li class="manager-projects__event'));
    $this->assertStringContainsString('events-note', $html);
    $this->assertStringContainsString('102', $html);
  }

  /**
   * Only the manager of the organization gets the events, and only with GET.
   */
  public function testAccess(): void {
    $project = $this->createProject(ProjectState::Open);
    $this->createLog($project);
    $denied = [
      'manager of another organization' => [$this->managerOf($this->createProject(ProjectState::Open)), 'GET'],
      'creative' => [$this->createCreative(), 'GET'],
      'POST of the manager' => [$this->managerOf($project), 'POST'],
    ];
    foreach ($denied as $label => [$account, $method]) {
      $response = $this->events($account, $project, $method);
      $this->assertSame(403, $response->getStatusCode(), $label);
      $this->assertStringNotContainsString('manager-projects__event', (string) $response->getContent(), $label);
    }
  }

}
