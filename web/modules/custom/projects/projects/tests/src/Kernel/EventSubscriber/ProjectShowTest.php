<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectShowEvent;
use Drupal\projects\EventSubscriber\Action\ProjectHideSubscriber;
use Drupal\projects\EventSubscriber\Action\ProjectShowSubscriber;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the project show subscriber.
 */
#[CoversMethod(ProjectShowSubscriber::class, 'onProjectShow')]
#[CoversMethod(ProjectHideSubscriber::class, 'getSubscribedEvents')]
#[Group('projects')]
class ProjectShowTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project show event listener.
   */
  public function testProjectShow(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setHidden();
    $project->save();
    $this->assertTrue($project->isHidden());

    $event = new ProjectShowEvent($project);
    $this->eventDispatcher->dispatch($event);

    $this->assertFalse($project->isHidden());
  }

  /**
   * Tests that archived projects can not be shown.
   */
  public function testProjectShowArchived(): void {
    $project = $this->createProject(ProjectState::Open);
    $project->setArchived()->setHidden();

    $this->expectException(\LogicException::class);
    $this->eventDispatcher->dispatch(new ProjectShowEvent($project));
  }

  /**
   * Tests that declined projects can not be shown.
   */
  public function testProjectShowDeclined(): void {
    $project = $this->createProject(ProjectState::Declined);
    $project->setHidden();

    $this->expectException(\LogicException::class);
    $this->eventDispatcher->dispatch(new ProjectShowEvent($project));
  }

}
