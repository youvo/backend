<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\projects\Access\ProjectEntityAccess;
use Drupal\projects\Access\ProjectResultUploadAccess;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project entity access with the site roles.
 */
#[CoversClass(ProjectEntityAccess::class)]
#[CoversClass(ProjectResultUploadAccess::class)]
#[Group('projects')]
class ProjectEntityAccessTest extends ProjectResourceTestBase {

  /**
   * Tests the access of the organization to its own projects.
   */
  #[DataProvider('stateProvider')]
  public function testOrganizationAccess(ProjectState $state): void {

    $project = $this->createProject($state);
    $organization = $project->getOwner();

    $this->assertTrue($project->access('view', $organization));
    $editable = in_array($state, [ProjectState::Draft, ProjectState::Pending, ProjectState::Open], TRUE);
    $this->assertSame($editable, $project->access('update', $organization));
    $deletable = in_array($state, [ProjectState::Draft, ProjectState::Pending], TRUE);
    $this->assertSame($deletable, $project->access('delete', $organization));
  }

  /**
   * Tests that prospects view, but neither edit nor delete their projects.
   */
  public function testProspectAccess(): void {

    $project = $this->createProject(ProjectState::Draft, 'prospect');
    $prospect = $project->getOwner();

    $this->assertTrue($project->access('view', $prospect));
    $this->assertFalse($project->access('update', $prospect));
    $this->assertFalse($project->access('delete', $prospect));
  }

  /**
   * Tests the access of managers.
   */
  #[DataProvider('stateProvider')]
  public function testManagerAccess(ProjectState $state): void {

    $project = $this->createProject($state);
    $manager = $project->getOwner()->getManager();
    $other_manager = $this->createManager();

    // The manager of the organization views and edits, but never deletes.
    $this->assertTrue($project->access('view', $manager));
    $this->assertTrue($project->access('update', $manager));
    $this->assertFalse($project->access('delete', $manager));

    // Other managers only view public states.
    $public = in_array($state, [ProjectState::Open, ProjectState::Ongoing, ProjectState::Completed], TRUE);
    $this->assertSame($public, $project->access('view', $other_manager));
    $this->assertFalse($project->access('update', $other_manager));
    $this->assertFalse($project->access('delete', $other_manager));
  }

  /**
   * Tests that managers view drafts of organizations without a manager.
   */
  public function testManagerViewsUnmanagedDraft(): void {

    $project = $this->createProject();
    $project->getOwner()->set('field_manager', NULL)->save();
    $manager = $this->createManager();

    $this->assertTrue($project->access('view', $manager));
    $this->assertFalse($project->access('update', $manager));
    $this->assertFalse($project->access('view', $this->createCreative()));
  }

  /**
   * Tests the access of supervisors and administrators.
   */
  #[DataProvider('stateProvider')]
  public function testSupervisorAndAdministratorAccess(ProjectState $state): void {

    $project = $this->createProject($state);
    $supervisor = $this->createSupervisor();
    $administrator = $this->createCreative('administrator');

    // Supervisors view and edit every project, but never delete.
    $this->assertTrue($project->access('view', $supervisor));
    $this->assertTrue($project->access('update', $supervisor));
    $this->assertFalse($project->access('delete', $supervisor));

    // Administrators may delete any project.
    $this->assertTrue($project->access('view', $administrator));
    $this->assertTrue($project->access('update', $administrator));
    $this->assertTrue($project->access('delete', $administrator));
  }

  /**
   * Tests that only supervisors access unpublished projects.
   */
  public function testUnpublishedAccess(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setUnpublished()->save();

    $this->assertTrue($project->access('view', $this->createSupervisor()));
    $this->assertFalse($project->access('view', $project->getOwner()));
  }

  /**
   * Tests the create access per role.
   */
  public function testCreateAccess(): void {
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('project');
    $accounts = [
      'organization' => $this->createOrganization(),
      'manager' => $this->createManager(),
      'supervisor' => $this->createSupervisor(),
      'administrator' => $this->createCreative('administrator'),
    ];
    foreach ($accounts as $role => $account) {
      $this->assertTrue($handler->createAccess('project', $account), "$role cannot create projects.");
    }
    $this->assertFalse($handler->createAccess('project', $this->createCreative()));
  }

  /**
   * Tests the result upload access per role.
   */
  public function testResultUploadAccess(): void {

    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('project_result');
    $context = ['entity_type_id' => 'project_result'];
    foreach ([$this->createCreative(), $this->createOrganization(), $this->createManager(), $this->createSupervisor()] as $account) {
      $this->assertTrue($handler->createAccess('project_result', $account, $context));
    }

    $result = $handler->createAccess('project_result', $this->createCreative('observer'), $context, TRUE);
    $this->assertFalse($result->isAllowed());
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('user.permissions', $result->getCacheContexts());
  }

  /**
   * Provides the lifecycle states except declined.
   */
  public static function stateProvider(): array {
    $states = [];
    foreach (ProjectState::cases() as $state) {
      if ($state !== ProjectState::Declined) {
        $states[$state->value] = [$state];
      }
    }
    return $states;
  }

}
