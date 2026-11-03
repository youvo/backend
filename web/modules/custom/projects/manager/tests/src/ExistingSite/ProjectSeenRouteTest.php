<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\manager\ProjectSeenTracker;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\RequestTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the routes that confirm projects, through the HTTP kernel.
 *
 * The requests run as the current user of the test process. The CSRF token
 * is generated in the same process, so a valid token passes. The site's
 * blocker mode answers every exception of a non-API path with a 403, so a
 * wrong method gives 403 instead of 405; the missing confirmation shows that
 * the route did not run.
 */
#[Group('manager')]
class ProjectSeenRouteTest extends ManagerExistingSiteTestBase {

  use RequestTrait;

  /**
   * Sends a request as an account.
   */
  protected function requestAs(AccountInterface $account, string $path, string $method = 'POST', ?string $token = NULL, array $query = []): Response {
    if ($token !== NULL) {
      $query['token'] = $token;
    }
    $this->actAs($account);
    return $this->doRequest(Request::create($path . ($query ? '?' . http_build_query($query) : ''), $method));
  }

  /**
   * Gets a valid CSRF token for a path.
   */
  protected function token(string $path): string {
    return $this->container->get('csrf_token')->get(ltrim($path, '/'));
  }

  /**
   * Gets the confirmed project ids of a user.
   *
   * @return int[]
   *   The project ids.
   */
  protected function confirmed(AccountInterface $account): array {
    $ids = $this->container->get('database')->select(ProjectSeenTracker::TABLE, 's')
      ->fields('s', ['project_id'])
      ->condition('uid', $account->id())
      ->orderBy('project_id')
      ->execute()
      ->fetchCol();
    return array_map('intval', $ids);
  }

  /**
   * Gets the path of the route that confirms a project.
   */
  protected function seenPath(ProjectInterface $project): string {
    return '/project/manage/seen/' . $project->id();
  }

  /**
   * The manager confirms a project and returns to the local destination.
   */
  public function testSeenConfirmsTheProject(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $path = $this->seenPath($project);
    $response = $this->requestAs($manager, $path, 'POST', $this->token($path), ['destination' => '/admin/dashboard']);
    $this->assertSame(302, $response->getStatusCode());
    $this->assertStringEndsWith('/admin/dashboard', (string) $response->headers->get('Location'));
    $this->assertSame([(int) $project->id()], $this->confirmed($manager));
  }

  /**
   * The route needs a POST and a valid token.
   */
  public function testSeenNeedsPostAndToken(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $path = $this->seenPath($project);
    $this->assertSame(403, $this->requestAs($manager, $path, 'GET', $this->token($path))->getStatusCode(), 'GET');
    $this->assertSame(403, $this->requestAs($manager, $path)->getStatusCode(), 'No token');
    $this->assertSame(403, $this->requestAs($manager, $path, 'POST', 'invalid')->getStatusCode(), 'Invalid token');
    $this->assertSame(403, $this->requestAs($manager, $path, 'POST', $this->token('/project/manage/seen'))->getStatusCode(), 'Token of another path');
    $this->assertSame([], $this->confirmed($manager));
  }

  /**
   * Only the manager of the organization may confirm a project.
   */
  public function testSeenDeniesOthers(): void {
    $project = $this->createProject(ProjectState::Open);
    $path = $this->seenPath($project);
    $others = [
      'manager of another organization' => $this->managerOf($this->createProject(ProjectState::Open)),
      'creative' => $this->createCreative(),
    ];
    foreach ($others as $label => $account) {
      $this->assertSame(403, $this->requestAs($account, $path, 'POST', $this->token($path))->getStatusCode(), $label);
      $this->assertSame([], $this->confirmed($account), $label);
    }
    $this->assertSame([], $this->confirmed($this->managerOf($project)));
  }

  /**
   * All projects can be confirmed at once, and only the own ones.
   */
  public function testSeenAllConfirmsOnlyOwnProjects(): void {
    $first = $this->createProject(ProjectState::Open);
    $second = $this->createProject(ProjectState::Ongoing, $first->getOwner());
    $foreign = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($first);
    $path = '/project/manage/seen';
    $this->assertSame(403, $this->requestAs($manager, $path, 'POST', 'invalid')->getStatusCode());
    $this->assertSame([], $this->confirmed($manager));
    $response = $this->requestAs($manager, $path, 'POST', $this->token($path));
    $this->assertSame(302, $response->getStatusCode());
    $this->assertSame([(int) $first->id(), (int) $second->id()], $this->confirmed($manager));
    $this->assertSame([], $this->confirmed($this->managerOf($foreign)));
  }

}
