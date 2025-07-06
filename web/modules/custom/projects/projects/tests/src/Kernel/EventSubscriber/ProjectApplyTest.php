<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\ProjectState;

/**
 * Tests for the project apply event subscriber.
 *
 * @coversDefaultClass \Drupal\projects\EventSubscriber\Action\ProjectApplySubscriber
 * @group projects
 */
class ProjectApplyTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project apply event listener.
   *
   * @covers ::onProjectApply
   * @covers ::getSubscribedEvents
   */
  public function testProjectApply(): void {

    $project = $this->createProject(ProjectState::Open);
    $this->assertTrue($project->lifecycle()->isOpen());

    $creative = $this->createCreative();
    $event = new ProjectApplyEvent($project, $creative);
    $event->setPhoneNumber('+1234567890');
    $this->eventDispatcher->dispatch($event);

    $this->assertTrue($project->lifecycle()->isOpen());
    $this->assertTrue($project->hasApplicant());
    $this->assertTrue($project->isApplicant($creative));

    $applicants = $project->getApplicants();
    $this->assertCount(1, $applicants);
    $applicant = reset($applicants);
    $this->assertEquals('+1234567890', $applicant->getPhoneNumber());
  }

}
