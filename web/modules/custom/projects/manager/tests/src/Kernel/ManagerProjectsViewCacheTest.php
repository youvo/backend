<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\manager\Hook\ManagerHooks;
use Drupal\projects\ProjectState;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the cacheability of the project manager view.
 *
 * The pane triggers depend on whether the user manages the organization, so a
 * manager reassignment must invalidate the view.
 */
#[CoversClass(ManagerHooks::class)]
#[Group('manager')]
class ManagerProjectsViewCacheTest extends ProjectSeenKernelTestBase {

  /**
   * Runs the pre render hook on a view with the given id and projects.
   */
  protected function preRender(string $id, array $projects): ViewExecutable {
    $view = $this->createMock(ViewExecutable::class);
    $view->method('id')->willReturn($id);
    $view->result = array_map(static fn($project) => new ResultRow(['_entity' => $project]), $projects);
    $view->element = [];
    $this->container->get('class_resolver')->getInstanceFromDefinition(ManagerHooks::class)->viewsPreRender($view);
    return $view;
  }

  /**
   * The view follows the user and the organizations of its projects.
   */
  public function testFollowsTheOrganizations(): void {

    $first = $this->createMailableProject(ProjectState::Open);
    $second = $this->createMailableProject(ProjectState::Draft);
    $this->assertNotSame($first->getOwner()->id(), $second->getOwner()->id());

    $view = $this->preRender('project_manager', [$first, $second]);

    $this->assertContains('user', $view->element['#cache']['contexts']);
    $this->assertContains('user:' . $first->getOwner()->id(), $view->element['#cache']['tags']);
    $this->assertContains('user:' . $second->getOwner()->id(), $view->element['#cache']['tags']);
  }

  /**
   * Other views are left alone.
   */
  public function testOtherViewsUnchanged(): void {
    $view = $this->preRender('other', [$this->createMailableProject(ProjectState::Open)]);
    $this->assertSame([], $view->element);
  }

}
