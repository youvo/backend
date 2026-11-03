<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\projects\Access\ProjectFieldAccess;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the cacheability of the project access results.
 */
#[Group('projects')]
class ProjectAccessCacheabilityTest extends ProjectResourceTestBase {

  /**
   * Tests the cache contexts of the entity and field access results.
   *
   * Results granted by identity vary per user, the others only per
   * permissions.
   */
  public function testCacheContexts(): void {

    $creative = $this->createCreative();
    $draft = $this->createProject(ProjectState::Draft);
    foreach (['view', 'update', 'delete'] as $operation) {
      $result = $draft->access($operation, $creative, TRUE);
      $this->assertFalse($result->isAllowed());
      $this->assertContains('user', $this->cacheContexts($result), "The $operation result does not vary per user.");
    }

    $unpublished = $this->createProject(ProjectState::Open);
    $unpublished->setUnpublished()->save();
    $result = $unpublished->access('view', $creative, TRUE);
    $this->assertTrue($result->isForbidden());
    $this->assertContains('user.permissions', $this->cacheContexts($result));
    $this->assertNotContains('user', $this->cacheContexts($result));

    $open = $this->createProject(ProjectState::Open);
    $result = ProjectFieldAccess::checkFieldAccess($open, 'view', $open->getFieldDefinition('field_applicants'), $creative);
    $this->assertTrue($result->isForbidden());
    $this->assertContains('user', $this->cacheContexts($result));
    $this->assertContains('user.permissions', $this->cacheContexts($result));

    $result = ProjectFieldAccess::checkFieldAccess($open, 'view', $open->getFieldDefinition('field_lifecycle_history'), $creative);
    $this->assertTrue($result->isForbidden());
    $this->assertContains('user.permissions', $this->cacheContexts($result));
    $this->assertNotContains('user', $this->cacheContexts($result));
  }

  /**
   * Tests the permission to view the fields of the project manager page.
   */
  public function testManagerFieldsRequirePermission(): void {

    $project = $this->createProject(ProjectState::Draft);
    $plain = $this->createCreative();
    $holder = $this->createHolder();

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
   */
  public function testManagerPermissionDoesNotGrantOtherFields(): void {

    $project = $this->createProject(ProjectState::Draft);
    $holder = $this->createHolder();
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

    // Spot check sensitive fields that must stay denied.
    foreach (['field_applicants', 'uuid', 'field_lifecycle_history'] as $name) {
      $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $project->getFieldDefinition($name), $holder);
      $this->assertTrue($result->isForbidden(), "$name is viewable with the permission.");
    }
  }

  /**
   * Tests that the manager page keeps working for managers.
   *
   * The expectation is computed from the active configuration: every project
   * field that the view reads is either viewable without the permission or
   * part of the manager fields, and the manager role holds the permission.
   */
  public function testManagerViewFieldsArePinned(): void {

    $config_factory = $this->container->get('config.factory');
    $permissions = $config_factory->get('user.role.manager')->get('permissions');
    $this->assertContains('view project manager fields', $permissions);
    $this->assertContains('general manager access', $permissions);

    $view = $config_factory->get('views.view.project_manager')->get();
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
    $holder = $this->createHolder();
    foreach ($names as $name) {
      $field = $project->getFieldDefinition($name);
      $this->assertNotNull($field, "The view reads the unknown project field $name.");
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
   * Creates a creative who also holds the permission for the manager fields.
   *
   * No site role holds only this permission, so a temporary role adds it.
   */
  protected function createHolder(): Creative {
    $holder = $this->createCreative();
    $holder->addRole($this->createRole(['view project manager fields']))->save();
    return $holder;
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
