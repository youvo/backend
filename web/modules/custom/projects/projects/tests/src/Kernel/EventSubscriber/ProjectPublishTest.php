<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;

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

    $project = $this->createProject(ProjectState::Pending);
    $this->assertTrue($project->lifecycle()->isPending());
    $event = new ProjectPublishEvent($project);
    $this->eventDispatcher->dispatch($event);
    $this->assertTrue($project->lifecycle()->isOpen());

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem $last */
    $last = $project->lifecycle()->history()->last();
    $this->assertEquals(ProjectTransition::Publish->value, $last->transition);
  }

}
