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

    $project = $this->createProject(ProjectState::Open);
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
    $this->assertEquals(ProjectTransition::Mediate->value, $last->transition);
  }

  /**
   * A selected manager is a participant once, as manager.
   */
  public function testSelectedManagerOnce(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();
    $creative = $this->createCreative();
    $project->appendApplicant($creative);
    $project->appendApplicant($manager);

    $event = new ProjectMediateEvent($project);
    $event->setCreatives([$creative, $manager]);
    $this->eventDispatcher->dispatch($event);

    $tasks = array_column($project->get('field_participants_tasks')->getValue(), 'value');
    $this->assertSame(['Creative', 'Manager'], $tasks);
  }

}
