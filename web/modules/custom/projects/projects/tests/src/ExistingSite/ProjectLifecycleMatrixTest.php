<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project transitions against the workflow of the site.
 */
#[Group('projects')]
class ProjectLifecycleMatrixTest extends ProjectResourceTestBase {

  /**
   * The states each transition starts from, for an organization project.
   *
   * The project has a participant. Drafts of prospects may also be declined.
   */
  protected const array ALLOWED = [
    'submit' => ['draft'],
    'publish' => ['pending'],
    'mediate' => ['open'],
    'complete' => ['ongoing'],
    'reset' => ['pending', 'open', 'ongoing', 'completed', 'declined'],
    'decline' => ['pending'],
  ];

  /**
   * Tries a transition from a state, in memory.
   */
  protected function tryTransition(ProjectInterface $project, ProjectState $from, ProjectTransition $transition): bool {
    $project->set(ProjectLifecycle::LIFECYCLE_FIELD, $from->value);
    $lifecycle = $project->lifecycle();
    try {
      return $lifecycle->{$transition->value}();
    }
    catch (LifecycleTransitionException) {
      $this->assertSame($from->value, $project->get(ProjectLifecycle::LIFECYCLE_FIELD)->value);
      return FALSE;
    }
  }

  /**
   * Tests that each project keeps its own lifecycle.
   */
  public function testLifecycleIsPerProject(): void {
    $open = $this->createProject(ProjectState::Open);
    $draft = $this->createProject();
    $open->lifecycle();
    $draft->lifecycle();
    $this->assertTrue($open->lifecycle()->isOpen());
    $this->assertTrue($draft->lifecycle()->isDraft());

    // A clone, as entity forms build it, follows its own state.
    $clone = clone $open;
    $clone->set(ProjectLifecycle::LIFECYCLE_FIELD, ProjectState::Draft->value);
    $this->assertTrue($clone->lifecycle()->isDraft());
    $this->assertTrue($open->lifecycle()->isOpen());
  }

  /**
   * Tests every transition from every state.
   */
  public function testTransitionMatrix(): void {

    $organization_project = $this->createProject();
    $organization_project->appendParticipant($this->createCreative())->save();
    $prospect_project = $this->createProject(ProjectState::Draft, 'prospect');
    $prospect_project->appendParticipant($this->createCreative())->save();

    foreach (ProjectTransition::cases() as $transition) {
      foreach (ProjectState::cases() as $state) {
        $label = "{$transition->value} from {$state->value}";
        $allowed = in_array($state->value, static::ALLOWED[$transition->value], TRUE);
        $this->assertSame($allowed, $this->tryTransition($organization_project, $state, $transition), $label);
        $allowed_for_prospect = $allowed || ($transition === ProjectTransition::Decline && $state === ProjectState::Draft);
        $this->assertSame($allowed_for_prospect, $this->tryTransition($prospect_project, $state, $transition), "$label of a prospect");
      }
    }

    // Mediate and complete require a participating creative.
    $project = $this->createProject();
    $this->assertFalse($this->tryTransition($project, ProjectState::Open, ProjectTransition::Mediate));
    $this->assertFalse($this->tryTransition($project, ProjectState::Ongoing, ProjectTransition::Complete));
  }

  /**
   * Tests that saving a project in an unchanged state passes validation.
   */
  public function testUnchangedStateIsValid(): void {
    foreach (ProjectState::cases() as $state) {
      $project = $this->createProject($state);
      $project->setTitle($this->randomString());
      $this->assertCount(0, $project->get(ProjectLifecycle::LIFECYCLE_FIELD)->validate(), $state->value);
    }
  }

  /**
   * Tests that a reset restores a declined project to a usable draft.
   */
  public function testDeclineThenReset(): void {

    $project = $this->createProject(ProjectState::Pending);
    $dispatcher = $this->container->get('event_dispatcher');

    $dispatcher->dispatch(new ProjectDeclineEvent($project));
    $this->assertTrue($project->lifecycle()->isDeclined());
    try {
      $dispatcher->dispatch(new ProjectSubmitEvent($project));
      $this->fail('A declined project was submitted.');
    }
    catch (LifecycleTransitionException) {
      $this->assertTrue($project->lifecycle()->isDeclined());
    }

    $dispatcher->dispatch(new ProjectResetEvent($project));
    $this->assertTrue($project->lifecycle()->isDraft());

    // The restored draft can enter the regular flow again.
    $dispatcher->dispatch(new ProjectSubmitEvent($project));
    $stored = $this->reloadProject($project);
    $this->assertLastTransition($stored, ProjectTransition::Submit, ProjectState::Draft, ProjectState::Pending);
    $this->assertTrue($stored->isPublished());
    $transitions = array_column($stored->lifecycle()->history()->getValue(), 'transition');
    $this->assertSame([NULL, 'decline', 'reset', 'submit'], $transitions);
  }

}
