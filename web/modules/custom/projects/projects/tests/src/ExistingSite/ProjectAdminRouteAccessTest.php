<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the project admin pages are for administrators only.
 *
 * The project page is for staff.
 */
#[Group('projects')]
class ProjectAdminRouteAccessTest extends ProjectResourceTestBase {

  /**
   * The project admin routes.
   */
  protected const array ROUTES = [
    'entity.project.add_form',
    'entity.project.edit_form',
    'entity.project.delete_form',
    'entity.project.collection',
    'entity.project.settings',
    'project.submit',
    'project.publish',
    'project.mediate',
    'project.complete',
    'project.reset',
    'project.unarchive',
  ];

  /**
   * Tests the route access per role.
   */
  public function testRouteAccess(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();

    $accounts = [
      'organization' => [$organization, FALSE],
      'manager' => [$organization->getManager(), FALSE],
      'supervisor' => [$this->createSupervisor(), FALSE],
      'administrator' => [$this->createCreative('administrator'), TRUE],
    ];
    foreach ($accounts as $label => [$account, $expected]) {
      foreach (static::ROUTES as $route) {
        $this->assertSame($expected, $this->checkRoute($route, $project, $account), "$label on $route");
      }
    }

  }

  /**
   * Tests that the project page is for staff, but the API keeps its access.
   */
  public function testCanonicalRouteAccess(): void {

    $project = $this->createProject(ProjectState::Open);
    $anonymous = new AnonymousUserSession();

    $this->assertFalse($this->checkRoute('entity.project.canonical', $project, $anonymous));
    $this->assertFalse($this->checkRoute('entity.project.canonical', $project, $project->getOwner()));
    $this->assertTrue($this->checkRoute('entity.project.canonical', $project, $project->getOwner()->getManager()));

    // JSON:API checks the entity access, which stays open.
    $this->assertTrue($project->access('view', $anonymous));
  }

  /**
   * Checks the access of an account to a route, with the project if needed.
   */
  protected function checkRoute(string $route, ProjectInterface $project, AccountInterface $account): bool {
    $path = $this->container->get('router.route_provider')->getRouteByName($route)->getPath();
    $parameters = str_contains($path, '{project}') ? ['project' => $project->id()] : [];
    return $this->container->get('access_manager')->checkNamedRoute($route, $parameters, $account);
  }

}
