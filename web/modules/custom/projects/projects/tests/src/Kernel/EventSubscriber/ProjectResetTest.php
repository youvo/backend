<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;

/**
 * Tests for the project reset event subscriber.
 *
 * @coversDefaultClass \Drupal\projects\EventSubscriber\Transition\ProjectResetSubscriber
 * @group projects
 */
class ProjectResetTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project reset event listener.
   *
   * @covers ::onProjectReset
   * @covers ::getSubscribedEvents
   */
  public function testProjectReset(): void {

    $project = $this->createProject(ProjectState::Open);
    $this->assertTrue($project->lifecycle()->isOpen());
    $event = new ProjectResetEvent($project);
    $this->eventDispatcher->dispatch($event);
    $this->assertTrue($project->lifecycle()->isDraft());

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem $last */
    $last = $project->lifecycle()->history()->last();
    $this->assertEquals(ProjectTransition::Reset->value, $last->transition);
  }

}
