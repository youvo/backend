<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectTransition;

/**
 * Tests for the project submit event subscriber.
 *
 * @coversDefaultClass \Drupal\projects\EventSubscriber\Transition\ProjectSubmitSubscriber
 * @group projects
 */
class ProjectSubmitTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project submit event listener.
   *
   * @covers ::onProjectSubmit
   * @covers ::getSubscribedEvents
   */
  public function testProjectSubmit(): void {

    $project = $this->createProject();
    $this->assertTrue($project->lifecycle()->isDraft());
    $event = new ProjectSubmitEvent($project);
    $this->eventDispatcher->dispatch($event);
    $this->assertTrue($project->lifecycle()->isPending());

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem $last */
    $last = $project->lifecycle()->history()->last();
    $this->assertEquals(ProjectTransition::SUBMIT->value, $last->transition);
  }

}
