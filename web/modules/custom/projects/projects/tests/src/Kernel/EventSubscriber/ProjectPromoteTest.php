<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectPromoteEvent;
use Drupal\projects\EventSubscriber\Action\ProjectPromoteSubscriber;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the project promote subscriber.
 */
#[CoversMethod(ProjectPromoteSubscriber::class, 'onProjectPromote')]
#[CoversMethod(ProjectPromoteSubscriber::class, 'getSubscribedEvents')]
#[Group('projects')]
class ProjectPromoteTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project promote event listener.
   */
  public function testProjectPromote(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setPromoted(FALSE);
    $project->save();
    $this->assertFalse($project->isPromoted());

    $event = new ProjectPromoteEvent($project);
    $this->eventDispatcher->dispatch($event);

    $this->assertTrue($project->isPromoted());
  }

}
