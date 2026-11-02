<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\Event\ProjectUnarchiveEvent;
use Drupal\projects\EventSubscriber\Action\ProjectArchiveSubscriber;
use Drupal\projects\EventSubscriber\Action\ProjectUnarchiveSubscriber;
use Drupal\projects\Exception\ProjectActionException;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the project archive and unarchive subscribers.
 */
#[CoversClass(ProjectArchiveSubscriber::class)]
#[CoversClass(ProjectUnarchiveSubscriber::class)]
#[Group('projects')]
class ProjectArchiveTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests that archive clears promote and unarchive keeps the lifecycle.
   */
  public function testArchiveAndUnarchive(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $project->setPromoted(TRUE);
    $project->save();

    $this->eventDispatcher->dispatch(new ProjectArchiveEvent($project));
    $this->assertTrue($project->isArchived());
    $this->assertFalse($project->isPromoted());
    $this->assertTrue($project->lifecycle()->isOngoing());

    $this->eventDispatcher->dispatch(new ProjectUnarchiveEvent($project));
    $this->assertFalse($project->isArchived());
    $this->assertFalse($project->isPromoted());
    $this->assertTrue($project->lifecycle()->isOngoing());
  }

  /**
   * Tests that archived projects can not be archived again.
   */
  public function testArchiveArchived(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setArchived();

    $this->expectException(ProjectActionException::class);
    $this->eventDispatcher->dispatch(new ProjectArchiveEvent($project));
  }

  /**
   * Tests that active projects can not be unarchived.
   */
  public function testUnarchiveActive(): void {

    $project = $this->createProject(ProjectState::Open);

    $this->expectException(ProjectActionException::class);
    $this->eventDispatcher->dispatch(new ProjectUnarchiveEvent($project));
  }

}
