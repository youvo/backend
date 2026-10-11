<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\projects\Access\ProjectFieldAccess;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the cacheability of the project access results.
 *
 * @coversDefaultClass \Drupal\projects\Access\ProjectEntityAccess
 * @group projects
 */
class ProjectAccessCacheabilityTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * Tests that denied entity access varies per user.
   *
   * @covers ::checkAccess
   */
  public function testEntityAccessDeniedVariesPerUser(): void {

    $project = $this->createProject(ProjectState::Draft);
    $creative = $this->createCreative();

    foreach (['view', 'update', 'delete'] as $operation) {
      $result = $project->access($operation, $creative, TRUE);
      $this->assertFalse($result->isAllowed());
      $this->assertContains('user', $this->cacheContexts($result), "The $operation result does not vary per user.");
    }
  }

  /**
   * Tests that the unpublished check only varies per role.
   *
   * @covers ::checkAccess
   */
  public function testEntityAccessUnpublishedVariesPerUser(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setUnpublished();
    $creative = $this->createCreative();

    $result = $project->access('view', $creative, TRUE);
    $this->assertTrue($result->isForbidden());
    $this->assertContains('user.roles:supervisor', $this->cacheContexts($result));
    $this->assertNotContains('user', $this->cacheContexts($result));
  }

  /**
   * Tests that the field access results vary per user and permissions.
   *
   * @covers \Drupal\projects\Access\ProjectFieldAccess::checkFieldAccess
   */
  public function testFieldAccessVariesPerUser(): void {

    $project = $this->createProject(ProjectState::Open);
    $creative = $this->createCreative();
    $field = $project->getFieldDefinition('field_applicants');

    $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $creative);
    $this->assertTrue($result->isForbidden());
    $this->assertContains('user', $this->cacheContexts($result));
    $this->assertContains('user.permissions', $this->cacheContexts($result));

    // Fields that are not granted by identity only vary per permissions.
    $field = $project->getFieldDefinition('field_lifecycle_history');
    $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $creative);
    $this->assertTrue($result->isForbidden());
    $this->assertContains('user.permissions', $this->cacheContexts($result));
    $this->assertNotContains('user', $this->cacheContexts($result));
  }

  /**
   * Tests the permission to view the fields of the project manager page.
   *
   * @covers \Drupal\projects\Access\ProjectFieldAccess::checkFieldAccess
   */
  public function testManagerFieldsRequirePermission(): void {

    $project = $this->createProject(ProjectState::Draft);
    $plain = $this->createCreative();
    $holder = $this->createAccountWithPermissions(['view project manager fields']);

    foreach (ProjectFieldAccess::MANAGER_FIELDS as $name) {
      $field = $project->getFieldDefinition($name);

      $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $plain);
      $this->assertTrue($result->isForbidden(), "$name is viewable without the permission.");

      $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $holder);
      $this->assertFalse($result->isForbidden(), "$name is not viewable with the permission.");
      $this->assertContains('user.permissions', $this->cacheContexts($result));

      // The permission never grants editing.
      $result = ProjectFieldAccess::checkFieldAccess($project, 'edit', $field, $holder);
      $this->assertTrue($result->isForbidden(), "$name is editable with the permission.");
    }
  }

  /**
   * Tests that the permission does not reveal any other field.
   *
   * @covers \Drupal\projects\Access\ProjectFieldAccess::checkFieldAccess
   */
  public function testManagerPermissionDoesNotGrantOtherFields(): void {

    $project = $this->createProject(ProjectState::Draft);
    $holder = $this->createAccountWithPermissions(['view project manager fields']);
    $plain = $this->createCreative();

    $checked = 0;
    foreach ($project->getFieldDefinitions() as $name => $field) {
      if (in_array($name, ProjectFieldAccess::MANAGER_FIELDS, TRUE)) {
        continue;
      }
      // The permission must not change anything for any other field.
      foreach (['view', 'edit'] as $operation) {
        $without = ProjectFieldAccess::checkFieldAccess($project, $operation, $field, $plain);
        $with = ProjectFieldAccess::checkFieldAccess($project, $operation, $field, $holder);
        $this->assertSame($without->isForbidden(), $with->isForbidden(), "The permission changes $operation access to $name.");
      }
      $checked++;
    }
    $this->assertGreaterThan(10, $checked);

    // Spot check a sensitive field that must stay denied.
    foreach (['field_applicants', 'uuid', 'field_lifecycle_history'] as $name) {
      $field = $project->getFieldDefinition($name);
      if ($field) {
        $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $holder);
        $this->assertTrue($result->isForbidden(), "$name is viewable with the permission.");
      }
    }
  }

  /**
   * Tests that the manager page keeps working for managers.
   *
   * The expectation is computed from the configuration: every project field
   * that the view reads is either viewable without the permission or part of
   * the manager fields, and the manager role holds the permission.
   */
  public function testManagerViewFieldsArePinned(): void {

    $sync = DRUPAL_ROOT . '/../config/sync';
    $role = Yaml::decode(file_get_contents($sync . '/user.role.manager.yml'));
    $this->assertContains('view project manager fields', $role['permissions']);
    $this->assertContains('general manager access', $role['permissions']);

    $view = Yaml::decode(file_get_contents($sync . '/views.view.project_manager.yml'));
    $this->assertSame('project_field_data', $view['base_table']);

    $names = [];
    $aliases = [];
    foreach ($view['display'] as $display) {
      $options = $display['display_options'] ?? [];
      foreach (['fields', 'filters', 'sorts', 'arguments'] as $section) {
        foreach ($options[$section] ?? [] as $id => $handler) {
          // Handlers on a relationship read fields of another entity.
          if (($handler['relationship'] ?? 'none') !== 'none' || ($handler['table'] ?? '') === 'views') {
            continue;
          }
          $name = $handler['entity_field'] ?? NULL;
          if (!$name && str_starts_with($handler['table'], 'project__')) {
            $name = substr($handler['table'], strlen('project__'));
          }
          $this->assertNotEmpty($name, "Cannot map $section handler $id to a project field.");
          $names[$name] = $name;
          $aliases[$id] = $name;
        }
      }
      // Rewrite tokens such as {{ id }} read other row values.
      foreach ($options['fields'] ?? [] as $handler) {
        $text = $handler['alter']['text'] ?? '';
        if (preg_match_all('/{{\s*(\w+)\s*}}/', $text, $matches)) {
          foreach ($matches[1] as $token) {
            if (isset($aliases[$token])) {
              $names[$aliases[$token]] = $aliases[$token];
            }
          }
        }
      }
    }
    $this->assertContains('id', $names);
    $this->assertContains('changed', $names);

    $project = $this->createProject(ProjectState::Draft);
    $plain = $this->createCreative();
    $holder = $this->createAccountWithPermissions(['view project manager fields']);
    foreach ($names as $name) {
      $field = $project->getFieldDefinition($name);
      // Fields of modules that are not installed here (e.g. taxonomy) fall
      // back to the field groups.
      if (!$field) {
        $this->assertTrue(
          ProjectFieldAccess::isFieldOfGroup($name, array_merge(ProjectFieldAccess::PUBLIC_FIELDS, ProjectFieldAccess::UNRESTRICTED_FIELDS, ProjectFieldAccess::MANAGER_FIELDS)),
          "The view reads the unknown project field $name."
        );
        continue;
      }
      $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $plain);
      if ($result->isForbidden()) {
        $this->assertContains($name, ProjectFieldAccess::MANAGER_FIELDS, "The view reads $name which is denied and not a manager field.");
        $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $holder);
        $this->assertFalse($result->isForbidden(), "A manager cannot view $name.");
      }
    }
    // No unnecessary fields: each manager field is read and denied today.
    foreach (ProjectFieldAccess::MANAGER_FIELDS as $name) {
      $this->assertArrayHasKey($name, $names, "$name is not read by the view.");
    }
  }

  /**
   * Creates an account holding the given permissions.
   *
   * @param string[] $permissions
   *   The permissions.
   */
  protected function createAccountWithPermissions(array $permissions): Creative {
    $role = Role::create(['id' => $this->randomMachineName(), 'label' => 'Test']);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    $account = Creative::create(['name' => $this->randomString()]);
    $account->addRole($role->id());
    $account->save();
    return $account;
  }

  /**
   * Returns the cache contexts of an access result.
   *
   * @return string[]
   *   The cache contexts.
   */
  protected function cacheContexts(AccessResultInterface $result): array {
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    return $result->getCacheContexts();
  }

}
