<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;

/**
 * Tests for the project mediate event subscriber.
 *
 * @coversDefaultClass \Drupal\projects\EventSubscriber\Transition\ProjectMediateSubscriber
 * @group projects
 */
class ProjectMediateTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project mediate event listener.
   *
   * @covers ::onProjectMediate
   * @covers ::getSubscribedEvents
   */
  public function testProjectMediate(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $this->assertTrue($project->lifecycle()->isOpen());

    $creative = $this->createCreative();
    $project->appendApplicant($creative);
    $this->assertTrue($project->hasApplicant());

    $event = new ProjectMediateEvent($project);
    $event->setCreatives([$creative]);
    $this->eventDispatcher->dispatch($event);

    $this->assertTrue($project->lifecycle()->isOngoing());
    $this->assertTrue($project->hasParticipant());
    $this->assertTrue($project->isParticipant($creative));

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem $last */
    $last = $project->lifecycle()->history()->last();
    $this->assertEquals(ProjectTransition::MEDIATE->value, $last->transition);
  }

}
