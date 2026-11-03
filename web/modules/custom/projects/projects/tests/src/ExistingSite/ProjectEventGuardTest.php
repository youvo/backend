<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\Event\ProjectCompleteEvent;
use Drupal\projects\Event\ProjectDemoteEvent;
use Drupal\projects\Event\ProjectHideEvent;
use Drupal\projects\Event\ProjectPromoteEvent;
use Drupal\projects\Event\ProjectShowEvent;
use Drupal\projects\Event\ProjectUnarchiveEvent;
use Drupal\projects\Exception\ProjectActionException;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the guards of the project event subscribers that REST can't reach.
 *
 * The resources refuse these cases in access(), and unarchiving is only
 * possible with the admin form. So the events are dispatched directly.
 */
#[Group('projects')]
class ProjectEventGuardTest extends ProjectResourceTestBase {

  /**
   * Dispatches an event and asserts that the subscriber refuses it.
   */
  protected function assertRefused(Event $event, string $label): void {
    try {
      $this->container->get('event_dispatcher')->dispatch($event);
      $this->fail("$label was not refused.");
    }
    catch (ProjectActionException) {
      $this->addToAssertionCount(1);
    }
  }

  /**
   * Creates a saved project with the given flags.
   */
  protected function createFlaggedProject(ProjectState $state, bool $archived = FALSE, bool $hidden = FALSE): ProjectInterface {
    $project = $this->createProject($state);
    $project->setArchived($archived)->setHidden($hidden)->save();
    return $project;
  }

  /**
   * Tests that archiving and unarchiving keep the lifecycle, once each.
   */
  public function testArchiveAndUnarchive(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $project->setPromoted(TRUE)->save();
    $dispatcher = $this->container->get('event_dispatcher');

    $dispatcher->dispatch(new ProjectArchiveEvent($project));
    $this->assertRefused(new ProjectArchiveEvent($project), 'Archiving an archived project');
    $stored = $this->reloadProject($project);
    $this->assertTrue($stored->isArchived());
    $this->assertFalse($stored->isPromoted());
    $this->assertTrue($stored->lifecycle()->isOngoing());

    $dispatcher->dispatch(new ProjectUnarchiveEvent($project));
    $this->assertRefused(new ProjectUnarchiveEvent($project), 'Unarchiving an active project');
    $stored = $this->reloadProject($project);
    $this->assertFalse($stored->isArchived());
    $this->assertFalse($stored->isPromoted());
    $this->assertTrue($stored->lifecycle()->isOngoing());
  }

  /**
   * Tests that declined projects are not promoted, demoted, hidden or shown.
   */
  public function testDeclinedProjectActions(): void {

    $project = $this->createProject(ProjectState::Declined);
    $this->assertRefused(new ProjectPromoteEvent($project), 'Promoting a declined project');
    $this->assertRefused(new ProjectHideEvent($project), 'Hiding a declined project');

    $project->setPromoted(TRUE)->setHidden()->save();
    $this->assertRefused(new ProjectDemoteEvent($project), 'Demoting a declined project');
    $this->assertRefused(new ProjectShowEvent($project), 'Showing a declined project');

    $stored = $this->reloadProject($project);
    $this->assertTrue($stored->isPromoted());
    $this->assertTrue($stored->isHidden());
  }

  /**
   * Tests that archived projects are neither promoted, hidden nor shown.
   */
  public function testArchivedProjectActions(): void {

    $this->assertRefused(new ProjectPromoteEvent($this->createFlaggedProject(ProjectState::Open, TRUE)), 'Promoting an archived project');
    $this->assertRefused(new ProjectHideEvent($this->createFlaggedProject(ProjectState::Open, TRUE)), 'Hiding an archived project');

    $project = $this->createFlaggedProject(ProjectState::Open, TRUE, TRUE);
    $this->assertRefused(new ProjectShowEvent($project), 'Showing an archived project');
    $this->assertTrue($this->reloadProject($project)->isHidden());
  }

  /**
   * Tests that hidden projects are not promoted.
   */
  public function testHiddenProjectNotPromoted(): void {
    $project = $this->createFlaggedProject(ProjectState::Open, FALSE, TRUE);
    $this->assertRefused(new ProjectPromoteEvent($project), 'Promoting a hidden project');
    $this->assertFalse($this->reloadProject($project)->isPromoted());
  }

  /**
   * Tests that the project stays ongoing if its results cannot be saved.
   */
  public function testCompleteRollsBack(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $project->appendParticipant($this->createCreative());
    $project->save();
    // Without a result, saving the results fails after the project save.
    $project->set('project_result', NULL);

    try {
      $this->container->get('event_dispatcher')->dispatch(new ProjectCompleteEvent($project));
      $this->fail('The complete event throws without a result.');
    }
    catch (EntityStorageException) {
    }

    $stored = $this->reloadProject($project);
    $this->assertTrue($stored->lifecycle()->isOngoing());
    $this->assertNotNull($stored->get('project_result')->target_id);
  }

}
