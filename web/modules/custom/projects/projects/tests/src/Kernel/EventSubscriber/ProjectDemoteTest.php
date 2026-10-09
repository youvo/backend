<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectDemoteEvent;
use Drupal\projects\EventSubscriber\Action\ProjectDemoteSubscriber;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the project demote subscriber.
 */
#[CoversMethod(ProjectDemoteSubscriber::class, 'onProjectDemote')]
#[CoversMethod(ProjectDemoteSubscriber::class, 'getSubscribedEvents')]
#[Group('projects')]
class ProjectDemoteTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project demote event listener.
   */
  public function testProjectDemote(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setPromoted(TRUE);
    $project->save();
    $this->assertTrue($project->isPromoted());

    $event = new ProjectDemoteEvent($project);
    $this->eventDispatcher->dispatch($event);

    $this->assertFalse($project->isPromoted());
  }

}
