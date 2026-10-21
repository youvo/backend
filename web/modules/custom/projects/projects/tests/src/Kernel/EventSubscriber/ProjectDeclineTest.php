<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\EventSubscriber\Transition\ProjectDeclineSubscriber;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the project decline subscriber.
 */
#[CoversMethod(ProjectDeclineSubscriber::class, 'onProjectDecline')]
#[CoversMethod(ProjectDeclineSubscriber::class, 'getSubscribedEvents')]
#[CoversClass(ProjectDeclineEvent::class)]
#[Group('projects')]
class ProjectDeclineTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests the project decline event listener.
   *
   * Drafts can only be declined if the organization is a prospect.
   */
  #[DataProvider('declinableStateProvider')]
  public function testProjectDecline(ProjectState $state): void {

    $project = $this->createProject($state, $state === ProjectState::Draft);
    $project->setPromoted(TRUE);

    $this->eventDispatcher->dispatch(new ProjectDeclineEvent($project));

    // The project is declined, but not draft or any other state.
    $this->assertTrue($project->lifecycle()->isDeclined());
    $this->assertFalse($project->lifecycle()->isDraft());
    $this->assertEquals(ProjectState::Declined->value, $project->get('field_lifecycle')->value);

    // Declining removes the promotion, but keeps the project published.
    $this->assertFalse($project->isPromoted());
    $this->assertTrue($project->isPublished());

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem $last */
    $last = $project->lifecycle()->history()->last();
    $this->assertEquals(ProjectTransition::Decline->value, $last->transition);
    $this->assertEquals($state->value, $last->from);
    $this->assertEquals(ProjectState::Declined->value, $last->to);
  }

  /**
   * Provides the states from which a project can be declined.
   */
  public static function declinableStateProvider(): array {
    return [
      ProjectState::Draft->value => [ProjectState::Draft],
      ProjectState::Pending->value => [ProjectState::Pending],
    ];
  }

  /**
   * Tests that a project can not be declined.
   */
  #[DataProvider('undeclinableStateProvider')]
  public function testProjectDeclineNotPossible(ProjectState $state, bool $prospect): void {
    $project = $this->createProject($state, $prospect);
    $this->expectException(LifecycleTransitionException::class);
    $this->eventDispatcher->dispatch(new ProjectDeclineEvent($project));
  }

  /**
   * Provides the states from which a project can not be declined.
   */
  public static function undeclinableStateProvider(): array {
    return [
      'draft-without-prospect' => [ProjectState::Draft, FALSE],
      ProjectState::Open->value => [ProjectState::Open, FALSE],
      ProjectState::Ongoing->value => [ProjectState::Ongoing, FALSE],
      ProjectState::Completed->value => [ProjectState::Completed, FALSE],
      ProjectState::Declined->value => [ProjectState::Declined, TRUE],
    ];
  }

  /**
   * Tests that declined projects can not be submitted or published.
   */
  public function testDeclinedProjectCanNotBeSubmitted(): void {
    $project = $this->createProject(ProjectState::Declined);
    $this->expectException(LifecycleTransitionException::class);
    $this->eventDispatcher->dispatch(new ProjectSubmitEvent($project));
  }

  /**
   * Tests that a reset restores a declined project to a usable draft.
   */
  public function testProjectDeclineThenReset(): void {

    $project = $this->createProject(ProjectState::Pending);
    $this->eventDispatcher->dispatch(new ProjectDeclineEvent($project));
    $this->assertTrue($project->lifecycle()->isDeclined());

    $this->eventDispatcher->dispatch(new ProjectResetEvent($project));
    $this->assertTrue($project->lifecycle()->isDraft());
    $this->assertTrue($project->isPublished());

    // The restored draft can enter the regular flow again.
    $this->eventDispatcher->dispatch(new ProjectSubmitEvent($project));
    $this->assertTrue($project->lifecycle()->isPending());
  }

  /**
   * Tests the project decline event.
   */
  public function testProjectDeclineEvent(): void {
    $event = new ProjectDeclineEvent($this->createProject());
    $this->assertEquals(ProjectDeclineReason::Custom, $event->getReason());
    $event->setReason(ProjectDeclineReason::Deadline);
    $this->assertEquals(ProjectDeclineReason::Deadline, $event->getReason());
  }

}
