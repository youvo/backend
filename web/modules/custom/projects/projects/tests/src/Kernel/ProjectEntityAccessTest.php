<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\projects\Access\ProjectEntityAccess;
use Drupal\projects\Access\ProjectResultUploadAccess;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project entity access per role, based on permissions.
 */
#[CoversClass(ProjectEntityAccess::class)]
#[CoversClass(ProjectResultUploadAccess::class)]
#[Group('projects')]
class ProjectEntityAccessTest extends ProjectEventSubscriberTestBase {

  /**
   * The roles with the project permissions of config/sync.
   */
  protected const array ROLES = [
    'organization' => ['create project', 'update own project', 'delete own project', 'upload project result files'],
    'manager' => ['create project', 'general manager access', 'update any project', 'upload project result files'],
    'supervisor' => [
      'administer projects',
      'create project',
      'general manager access',
      'update any project',
      'upload project result files',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'entity',
    'language',
    'system',
    'user_types',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The first user bypasses all permissions. Create it up front.
    $this->createCreative();
    foreach (static::ROLES as $id => $permissions) {
      $role = Role::create(['id' => $id, 'label' => $id]);
      foreach ($permissions as $permission) {
        $role->grantPermission($permission);
      }
      $role->save();
    }
    Role::create(['id' => 'administrator', 'label' => 'Administrator', 'is_admin' => TRUE])->save();
  }

  /**
   * Tests that the roles in config/sync hold the permissions of this test.
   */
  public function testRolePermissionsArePinned(): void {
    $sync = DRUPAL_ROOT . '/../config/sync';
    foreach (static::ROLES as $id => $permissions) {
      $role = Yaml::decode(file_get_contents("$sync/user.role.$id.yml"));
      foreach ($permissions as $permission) {
        $this->assertContains($permission, $role['permissions'], "$id lacks $permission.");
      }
    }
    foreach (['manager', 'supervisor'] as $id) {
      $role = Yaml::decode(file_get_contents("$sync/user.role.$id.yml"));
      $this->assertNotContains('delete any project', $role['permissions'], "$id may delete any project.");
    }
    $role = Yaml::decode(file_get_contents("$sync/user.role.creative.yml"));
    $this->assertContains('upload project result files', $role['permissions']);
  }

  /**
   * Tests the access of the organization to its own projects.
   */
  #[DataProvider('stateProvider')]
  public function testOrganizationAccess(ProjectState $state): void {
    $project = $this->createSavedProject($state);
    $organization = $project->getOwner();
    $organization->addRole('organization')->save();

    $this->assertTrue($project->access('view', $organization));
    $editable = in_array($state, [ProjectState::Draft, ProjectState::Pending, ProjectState::Open], TRUE);
    $this->assertSame($editable, $project->access('update', $organization));
    $deletable = in_array($state, [ProjectState::Draft, ProjectState::Pending], TRUE);
    $this->assertSame($deletable, $project->access('delete', $organization));
  }

  /**
   * Tests that the organization needs the permissions to edit and delete.
   */
  public function testOrganizationWithoutPermissions(): void {
    $project = $this->createSavedProject(ProjectState::Draft);
    $organization = $project->getOwner();

    $this->assertTrue($project->access('view', $organization));
    $this->assertFalse($project->access('update', $organization));
    $this->assertFalse($project->access('delete', $organization));
  }

  /**
   * Tests the access of managers.
   */
  #[DataProvider('stateProvider')]
  public function testManagerAccess(ProjectState $state): void {
    $project = $this->createSavedProject($state);
    $manager = $project->getOwner()->getManager();
    $manager->addRole('manager')->save();
    $other_manager = $this->createCreative()->addRole('manager');
    $other_manager->save();

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
    $project = $this->createSavedProject(ProjectState::Draft);
    $organization = $project->getOwner();
    $organization->set('field_manager', NULL)->save();
    $manager = $this->createCreative()->addRole('manager');
    $manager->save();
    $creative = $this->createCreative();

    $this->assertTrue($project->access('view', $manager));
    $this->assertFalse($project->access('update', $manager));
    $this->assertFalse($project->access('view', $creative));
  }

  /**
   * Tests the access of supervisors and administrators.
   */
  #[DataProvider('stateProvider')]
  public function testSupervisorAndAdministratorAccess(ProjectState $state): void {
    $project = $this->createSavedProject($state);
    $supervisor = $this->createCreative()->addRole('supervisor');
    $supervisor->save();
    $administrator = $this->createCreative()->addRole('administrator');
    $administrator->save();

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
   * Tests that supervisors also access unpublished projects.
   */
  public function testSupervisorAccessUnpublished(): void {
    $project = $this->createSavedProject(ProjectState::Open);
    $project->setUnpublished()->save();
    $supervisor = $this->createCreative()->addRole('supervisor');
    $supervisor->save();
    $organization = $project->getOwner();
    $organization->addRole('organization')->save();

    $this->assertTrue($project->access('view', $supervisor));
    $this->assertFalse($project->access('view', $organization));
  }

  /**
   * Tests the create access per role.
   */
  public function testCreateAccess(): void {
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('project');
    foreach (['organization', 'manager', 'supervisor', 'administrator'] as $role) {
      $account = $this->createCreative()->addRole($role);
      $account->save();
      $this->assertTrue($handler->createAccess('project', $account), "$role cannot create projects.");
    }
    $this->assertFalse($handler->createAccess('project', $this->createCreative()));
  }

  /**
   * Tests the result upload access per permission.
   */
  public function testResultUploadAccess(): void {
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('project_result');
    $organization = $this->createCreative()->addRole('organization');
    $organization->save();

    $result = $handler->createAccess('project_result', $organization, ['entity_type_id' => 'project_result'], TRUE);
    $this->assertTrue($result->isAllowed());
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('user.permissions', $result->getCacheContexts());
    $this->assertFalse($handler->createAccess('project_result', $this->createCreative(), ['entity_type_id' => 'project_result']));
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

  /**
   * Creates and saves a project.
   */
  protected function createSavedProject(ProjectState $state): ProjectInterface {
    $project = $this->createProject($state);
    $project->save();
    return $project;
  }

}
