<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\ProjectState;

/**
 * Tests for the project publish event subscriber.
 *
 * @coversDefaultClass \Drupal\projects\EventSubscriber\Transition\ProjectPublishSubscriber
 * @group projects
 */
class ProjectPublishTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project publish event listener.
   *
   * @covers ::onProjectPublish
   * @covers ::getSubscribedEvents
   */
  public function testProjectPublish(): void {
    $project = $this->createProject(ProjectState::PENDING);
    $this->assertTrue($project->lifecycle()->isPending());
    $event = new ProjectPublishEvent($project);
    $this->eventDispatcher->dispatch($event);
    $this->assertTrue($project->lifecycle()->isOpen());
  }

}
