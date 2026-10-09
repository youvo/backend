<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectHideEvent;
use Drupal\projects\EventSubscriber\Action\ProjectHideSubscriber;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the project hide subscriber.
 */
#[CoversMethod(ProjectHideSubscriber::class, 'onProjectHide')]
#[CoversMethod(ProjectHideSubscriber::class, 'getSubscribedEvents')]
#[Group('projects')]
class ProjectHideTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project hide event listener.
   */
  public function testProjectHide(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setPromoted(TRUE);
    $project->setPublished();
    $project->save();
    $this->assertTrue($project->isPromoted());
    $this->assertTrue($project->isPublished());

    $event = new ProjectHideEvent($project);
    $this->eventDispatcher->dispatch($event);

    $this->assertFalse($project->isPromoted());
    $this->assertFalse($project->isPublished());
  }

}
