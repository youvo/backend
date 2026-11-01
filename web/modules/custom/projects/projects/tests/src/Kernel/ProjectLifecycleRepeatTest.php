<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that transitions are not repeatable and unchanged saves are valid.
 */
#[CoversClass(ProjectLifecycle::class)]
#[Group('projects')]
class ProjectLifecycleRepeatTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests that a transition is refused in its own target state.
   */
  #[DataProvider('transitionProvider')]
  public function testTransitionRefusedInTargetState(ProjectTransition $transition, ProjectState $target): void {
    // A prospect owner and a participant satisfy the other conditions.
    $project = $this->createProject($target, TRUE);
    $project->appendParticipant($this->createCreative());
    $project->save();

    $this->expectException(LifecycleTransitionException::class);
    match ($transition) {
      ProjectTransition::Submit => $project->lifecycle()->submit(),
      ProjectTransition::Publish => $project->lifecycle()->publish(),
      ProjectTransition::Mediate => $project->lifecycle()->mediate(),
      ProjectTransition::Complete => $project->lifecycle()->complete(),
      ProjectTransition::Reset => $project->lifecycle()->reset(),
      ProjectTransition::Decline => $project->lifecycle()->decline(),
    };
  }

  /**
   * Tests that saving a project in an unchanged state passes validation.
   */
  #[DataProvider('stateProvider')]
  public function testUnchangedStateIsValid(ProjectState $state): void {
    $project = $this->createProject($state);
    $project->save();
    $project->setTitle($this->randomString());

    $this->assertCount(0, $project->get(ProjectLifecycle::LIFECYCLE_FIELD)->validate());
  }

  /**
   * Provides each transition with its target state.
   */
  public static function transitionProvider(): array {
    return [
      'submit' => [ProjectTransition::Submit, ProjectState::Pending],
      'publish' => [ProjectTransition::Publish, ProjectState::Open],
      'mediate' => [ProjectTransition::Mediate, ProjectState::Ongoing],
      'complete' => [ProjectTransition::Complete, ProjectState::Completed],
      'reset' => [ProjectTransition::Reset, ProjectState::Draft],
      'decline' => [ProjectTransition::Decline, ProjectState::Declined],
    ];
  }

  /**
   * Provides all lifecycle states.
   */
  public static function stateProvider(): array {
    $states = [];
    foreach (ProjectState::cases() as $state) {
      $states[$state->value] = [$state];
    }
    return $states;
  }

}
