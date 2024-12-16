<?php

declare(strict_types=1);

namespace Drupal\Tests\projects\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\Plugin\WorkflowType\Lifecycle;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectLifecycle;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\Tests\UnitTestCase;
use Drupal\workflows\Entity\Workflow;
use Prophecy\Argument;

/**
 * Tests the project lifecycle.
 *
 * @coversDefaultClass \Drupal\projects\ProjectLifecycle
 * @group projects
 */
class ProjectLifecycleTest extends UnitTestCase {

  /**
   * The project lifecycle.
   */
  protected ProjectLifecycle $lifecycle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $this->lifecycle = new ProjectLifecycle($entity_type_manager->reveal());
  }

  /**
   * Tests the constructor.
   *
   * @covers ::__construct()
   */
  public function testConstructor(): void {
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $lifecycle = new ProjectLifecycle($entity_type_manager->reveal());
    $this->assertInstanceOf(ProjectLifecycle::class, $lifecycle);
    $this->assertObjectHasProperty('entityTypeManager', $lifecycle);
  }

  /**
   * Tests the setProject method.
   *
   * @covers ::setProject
   */
  public function testSetProject(): void {
    $project = $this->prophesize(ProjectInterface::class)->reveal();
    $self = $this->lifecycle->setProject($project);
    $this->assertInstanceOf(ProjectLifecycle::class, $self);
    $this->assertSame($project, $this->lifecycle->project());
  }

  /**
   * Tests the project method.
   *
   * @covers ::project
   */
  public function testProject(): void {

    $project = $this->prophesize(ProjectInterface::class)->reveal();
    $this->lifecycle->setProject($project);
    $this->assertSame($project, $this->lifecycle->project());

    // Test exception when project is not set properly.
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $empty_lifecycle = new ProjectLifecycle($entity_type_manager->reveal());
    $this->expectException(\UnexpectedValueException::class);
    $empty_lifecycle->project();
  }

  /**
   * Tests the different is methods.
   *
   * @covers ::isDraft
   * @covers ::isPending
   * @covers ::isOpen
   * @covers ::isOngoing
   * @covers ::isCompleted
   * @covers ::getState
   *
   * @dataProvider isStateProvider
   */
  public function testIsState(ProjectState $state): void {
    $method = 'is' . ucfirst($state->value);
    foreach (ProjectState::cases() as $test_state) {
      $this->prophesizeProject($test_state);
      $this->assertEquals($state === $test_state, $this->lifecycle->{$method}());
    }
  }

  /**
   * Provides data for the testIsState method.
   */
  public static function isStateProvider(): array {
    $cases = [];
    foreach (ProjectState::cases() as $state) {
      $cases[$state->value] = ['test_state' => $state];
    }
    return $cases;
  }

  /**
   * Prophesizes a project in the lifecycle with given state.
   */
  protected function prophesizeProject(ProjectState $state): void {
    $project = $this->prophesize(ProjectInterface::class);
    $value = (object) ['value' => $state->value];
    $project->get(Argument::any())->willReturn($value);
    $this->lifecycle->setProject($project->reveal());
  }

  /**
   * Tests the different transition methods.
   *
   * @param \Drupal\projects\ProjectTransition $transition
   *   The transition.
   * @param array $allowed_from
   *   An array to mock the workflow allowed from settings.
   * @param array $has_transition_return_values
   *   An array to mock the workflow transition settings.
   * @param bool $has_applicant
   *   Whether the project has an applicant. Relevant for mediate transition.
   *
   * @covers ::submit
   * @covers ::publish
   * @covers ::mediate
   * @covers ::complete
   * @covers ::reset
   * @covers ::hasTransition
   * @covers ::canTransition
   * @covers ::doTransition
   * @covers ::getSuccessorFromTransition
   *
   * @dataProvider doTransitionProvider
   */
  public function testDoTransition(ProjectTransition $transition, array $allowed_from, array $has_transition_return_values, bool $has_applicant): void {
    $this->prophesizeWorkflow($has_transition_return_values, $has_applicant);
    foreach (ProjectState::cases() as $state) {
      try {
        $this->assertTrue($this->lifecycle->{$transition->value}());
      }
      catch (LifecycleTransitionException) {
        $this->assertFalse(in_array($state, $allowed_from, TRUE) && $has_applicant);
      }
    }
  }

  /**
   * Provides data for the testDoTransition method.
   */
  public static function doTransitionProvider(): array {

    $cases[ProjectTransition::SUBMIT->value] = [
      'transition' => ProjectTransition::SUBMIT,
      'allowed_from' => [ProjectState::DRAFT],
      'has_transition_return_values' => [TRUE, FALSE, FALSE, FALSE, FALSE],
      // Not relevant for this case.
      'has_applicant' => FALSE,
    ];

    $cases[ProjectTransition::PUBLISH->value] = [
      'transition' => ProjectTransition::PUBLISH,
      'allowed_from' => [ProjectState::PENDING],
      'has_transition_return_values' => [FALSE, TRUE, FALSE, FALSE, FALSE],
      // Not relevant for this case.
      'has_applicant' => FALSE,
    ];

    $cases[ProjectTransition::MEDIATE->value . '-without-applicant'] = [
      'transition' => ProjectTransition::MEDIATE,
      'allowed_from' => [ProjectState::OPEN],
      'has_transition_return_values' => [FALSE, FALSE, FALSE, FALSE, FALSE],
      'has_applicant' => FALSE,
    ];

    $cases[ProjectTransition::MEDIATE->value . '-with-applicant'] = [
      'transition' => ProjectTransition::MEDIATE,
      'allowed_from' => [ProjectState::OPEN],
      'has_transition_return_values' => [FALSE, FALSE, TRUE, FALSE, FALSE],
      'has_applicant' => TRUE,
    ];

    $cases[ProjectTransition::COMPLETE->value] = [
      'transition' => ProjectTransition::COMPLETE,
      'allowed_from' => [ProjectState::ONGOING],
      'has_transition_return_values' => [FALSE, FALSE, FALSE, TRUE, FALSE],
      // Not relevant for this case.
      'has_applicant' => FALSE,
    ];

    $cases[ProjectTransition::RESET->value] = [
      'transition' => ProjectTransition::RESET,
      'allowed_from' => ProjectState::cases(),
      'has_transition_return_values' => [TRUE, TRUE, TRUE, TRUE, TRUE],
      // Not relevant for this case.
      'has_applicant' => FALSE,
    ];

    return $cases;
  }

  /**
   * Prophesizes a project lifecycle with different workflow conditions.
   */
  protected function prophesizeWorkflow(array $has_transition_return_values, bool $has_applicant): void {

    $lifecycle = $this->prophesize(Lifecycle::class);
    $lifecycle->hasTransition(Argument::any())->willReturn(...$has_transition_return_values);

    $workflow = $this->prophesize(Workflow::class);
    $workflow->getTypePlugin()->willReturn($lifecycle->reveal());

    $workflow_storage = $this->prophesize(EntityStorageInterface::class);
    $workflow_storage->load(Argument::any())->willReturn($workflow->reveal());

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('workflow')->willReturn($workflow_storage->reveal());

    $project = $this->prophesize(ProjectInterface::class);
    $project->set(Argument::any(), Argument::any())->willReturn(TRUE);
    $project->hasApplicant()->willReturn($has_applicant);

    $this->lifecycle = new ProjectLifecycle($entity_type_manager->reveal());
    $this->lifecycle->setProject($project->reveal());
  }

}
