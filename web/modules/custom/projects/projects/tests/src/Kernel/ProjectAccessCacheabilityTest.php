<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\projects\Access\ProjectFieldAccess;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;

/**
 * Tests the cacheability of the project access results.
 *
 * @coversDefaultClass \Drupal\projects\Access\ProjectEntityAccess
 * @group projects
 */
class ProjectAccessCacheabilityTest extends ProjectEventSubscriberTestBase {

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
