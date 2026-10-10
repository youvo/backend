<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\EventSubscriber\Transition\ProjectArchiveSubscriber;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the project archive subscriber.
 */
#[CoversMethod(ProjectArchiveSubscriber::class, 'onProjectArchive')]
#[CoversMethod(ProjectArchiveSubscriber::class, 'getSubscribedEvents')]
#[CoversClass(ProjectArchiveEvent::class)]
#[Group('projects')]
class ProjectArchiveTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project archive event listener.
   */
  #[DataProvider('archivableStateProvider')]
  public function testProjectArchive(ProjectState $state): void {

    $project = $this->createProject($state);
    $project->setPromoted(TRUE);
    $this->assertEquals($state === ProjectState::Draft, $project->lifecycle()->isDraft());

    $event = new ProjectArchiveEvent($project);
    $event->setReason(ProjectArchiveReason::External)->setMessage('Mediated elsewhere.');
    $this->eventDispatcher->dispatch($event);

    // The project is archived, but not draft or any other state.
    $this->assertTrue($project->lifecycle()->isArchived());
    $this->assertFalse($project->lifecycle()->isDraft());
    $this->assertEquals(ProjectState::Archived->value, $project->get('field_lifecycle')->value);

    // Archiving removes the promotion, but keeps the project published.
    $this->assertFalse($project->isPromoted());
    $this->assertTrue($project->isPublished());

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem $last */
    $last = $project->lifecycle()->history()->last();
    $this->assertEquals(ProjectTransition::Archive->value, $last->transition);
    $this->assertEquals($state->value, $last->from);
    $this->assertEquals(ProjectState::Archived->value, $last->to);
  }

  /**
   * Provides the states from which a project can be archived.
   */
  public static function archivableStateProvider(): array {
    return [
      ProjectState::Draft->value => [ProjectState::Draft],
      ProjectState::Pending->value => [ProjectState::Pending],
      ProjectState::Open->value => [ProjectState::Open],
      ProjectState::Ongoing->value => [ProjectState::Ongoing],
    ];
  }

  /**
   * Tests that completed and archived projects can not be archived.
   */
  #[DataProvider('unarchivableStateProvider')]
  public function testProjectArchiveNotPossible(ProjectState $state): void {
    $project = $this->createProject($state);
    $this->expectException(LifecycleTransitionException::class);
    $this->eventDispatcher->dispatch(new ProjectArchiveEvent($project));
  }

  /**
   * Provides the states from which a project can not be archived.
   */
  public static function unarchivableStateProvider(): array {
    return [
      ProjectState::Completed->value => [ProjectState::Completed],
      ProjectState::Archived->value => [ProjectState::Archived],
    ];
  }

  /**
   * Tests that archived projects can not be submitted or published.
   */
  public function testArchivedProjectCanNotBeSubmitted(): void {
    $project = $this->createProject(ProjectState::Archived);
    $this->expectException(LifecycleTransitionException::class);
    $this->eventDispatcher->dispatch(new ProjectSubmitEvent($project));
  }

  /**
   * Tests that a reset restores an archived project to a usable draft.
   */
  public function testProjectArchiveThenReset(): void {

    $project = $this->createProject(ProjectState::Open);
    $this->eventDispatcher->dispatch(new ProjectArchiveEvent($project));
    $this->assertTrue($project->lifecycle()->isArchived());

    $this->eventDispatcher->dispatch(new ProjectResetEvent($project));
    $this->assertTrue($project->lifecycle()->isDraft());
    $this->assertTrue($project->isPublished());

    // The restored draft can enter the regular flow again.
    $this->eventDispatcher->dispatch(new ProjectSubmitEvent($project));
    $this->assertTrue($project->lifecycle()->isPending());
  }

  /**
   * Tests the project archive event.
   */
  public function testProjectArchiveEvent(): void {
    $event = new ProjectArchiveEvent($this->createProject());
    $this->assertNull($event->getReason());
    $this->assertEquals('', $event->getMessage());
    $event->setReason(ProjectArchiveReason::Creatives)->setMessage('Aborted.');
    $this->assertEquals(ProjectArchiveReason::Creatives, $event->getReason());
    $this->assertEquals('Aborted.', $event->getMessage());
  }

}
