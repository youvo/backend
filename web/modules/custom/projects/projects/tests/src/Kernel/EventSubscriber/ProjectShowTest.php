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
    $project->setUnpublished();
    $project->save();
    $this->assertFalse($project->isPublished());

    $event = new ProjectShowEvent($project);
    $this->eventDispatcher->dispatch($event);

    $this->assertTrue($project->isPublished());
  }

}
