<?php

declare(strict_types=1);

namespace Drupal\Tests\projects\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\lifecycle\Plugin\WorkflowType\Lifecycle;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\workflows\Entity\Workflow;
use Prophecy\Argument;

/**
 * Tests the project lifecycle.
 *
 * @coversDefaultClass \Drupal\projects\Service\ProjectLifecycle
 * @group projects
 */
class ProjectLifecycleTest extends UnitTestCase {

  /**
   * The project lifecycle.
   */
  protected ProjectLifecycleInterface $lifecycle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $current_user = $this->prophesize(AccountProxyInterface::class);
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $time = $this->prophesize(TimeInterface::class);
    $this->lifecycle = new ProjectLifecycle(
      $current_user->reveal(),
      $entity_type_manager->reveal(),
      $time->reveal(),
    );
  }

  /**
   * Tests the project methods.
   *
   * @covers ::__construct()
   * @covers ::setProject
   * @covers ::project
   */
  public function testProject(): void {

    $project = $this->prophesize(ProjectInterface::class)->reveal();
    $self = $this->lifecycle->setProject($project);
    $this->assertInstanceOf(ProjectLifecycle::class, $self);
    $this->assertSame($project, $this->lifecycle->project());

    // Test exception when project is not set properly.
    $current_user = $this->prophesize(AccountProxyInterface::class)->reveal();
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class)->reveal();
    $time = $this->prophesize(TimeInterface::class)->reveal();
    $empty_lifecycle = new ProjectLifecycle($current_user, $entity_type_manager, $time);
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
   * @param array $has_transition
   *   An array to mock the workflow transition settings.
   * @param bool $has_participant
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
   * @covers ::inscribeTransition
   *
   * @dataProvider doTransitionProvider
   */
  public function testDoTransition(ProjectTransition $transition, array $allowed_from, array $has_transition, bool $has_participant): void {
    $states = ProjectState::cases();
    $this->prophesizeWorkflow($states, $has_transition, $has_participant);
    foreach ($states as $state) {
      try {
        $this->assertTrue($this->lifecycle->{$transition->value}());
      }
      catch (LifecycleTransitionException) {
        $this->assertFalse(in_array($state, $allowed_from, TRUE) && $has_participant);
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
      'has_transition' => [TRUE, FALSE, FALSE, FALSE, FALSE],
      // Not relevant for this case.
      'has_participant' => FALSE,
    ];

    $cases[ProjectTransition::PUBLISH->value] = [
      'transition' => ProjectTransition::PUBLISH,
      'allowed_from' => [ProjectState::PENDING],
      'has_transition' => [FALSE, TRUE, FALSE, FALSE, FALSE],
      // Not relevant for this case.
      'has_participant' => FALSE,
    ];

    $cases[ProjectTransition::MEDIATE->value . '-without-participant'] = [
      'transition' => ProjectTransition::MEDIATE,
      'allowed_from' => [ProjectState::OPEN],
      'has_transition' => [FALSE, FALSE, FALSE, FALSE, FALSE],
      'has_participant' => FALSE,
    ];

    $cases[ProjectTransition::MEDIATE->value . '-with-participant'] = [
      'transition' => ProjectTransition::MEDIATE,
      'allowed_from' => [ProjectState::OPEN],
      'has_transition' => [FALSE, FALSE, TRUE, FALSE, FALSE],
      'has_participant' => TRUE,
    ];

    $cases[ProjectTransition::COMPLETE->value] = [
      'transition' => ProjectTransition::COMPLETE,
      'allowed_from' => [ProjectState::ONGOING],
      'has_transition' => [FALSE, FALSE, FALSE, TRUE, FALSE],
      'has_participant' => TRUE,
    ];

    $cases[ProjectTransition::RESET->value] = [
      'transition' => ProjectTransition::RESET,
      'allowed_from' => ProjectState::cases(),
      'has_transition' => [TRUE, TRUE, TRUE, TRUE, TRUE],
      // Not relevant for this case.
      'has_participant' => FALSE,
    ];

    return $cases;
  }

  /**
   * Tests the history method.
   *
   * @covers ::history
   */
  public function testHistory(): void {
    $project = $this->prophesize(ProjectInterface::class);
    $history = $this->prophesize(FieldItemListInterface::class)->reveal();
    $project->get(Argument::any())->willReturn($history);
    $this->lifecycle->setProject($project->reveal());
    $this->assertSame($history, $this->lifecycle->history());
  }

  /**
   * Prophesizes a project lifecycle with different workflow conditions.
   */
  protected function prophesizeWorkflow(array $states, array $has_transition, bool $has_participant): void {

    $lifecycle_config = $this->prophesize(Lifecycle::class);
    $lifecycle_config->hasTransitionFromStateToState(Argument::any(), Argument::any())
      ->willReturn(...$has_transition);

    $workflow = $this->prophesize(Workflow::class);
    $workflow->getTypePlugin()->willReturn($lifecycle_config->reveal());

    $workflow_storage = $this->prophesize(EntityStorageInterface::class);
    $workflow_storage->load(Argument::any())->willReturn($workflow->reveal());

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('workflow')->willReturn($workflow_storage->reveal());

    $history = $this->prophesize(FieldItemListInterface::class);
    $history->appendItem(Argument::any())->willReturn(NULL);

    $values = array_map(static fn($s) => (object) ['value' => $s->value], $states);
    $project = $this->prophesize(ProjectInterface::class);
    $project->get(Argument::is(ProjectLifecycle::LIFECYCLE_FIELD))->willReturn(...$values);
    $project->get(Argument::is(ProjectLifecycle::LIFECYCLE_HISTORY_FIELD))->willReturn($history);
    $project->set(Argument::any(), Argument::any())->willReturn(TRUE);
    $project->hasParticipant(Argument::is('Creative'))->willReturn($has_participant);

    $current_user = $this->prophesize(AccountProxyInterface::class);
    $time = $this->prophesize(TimeInterface::class);

    $this->lifecycle = new ProjectLifecycle(
      $current_user->reveal(),
      $entity_type_manager->reveal(),
      $time->reveal()
    );
    $this->lifecycle->setProject($project->reveal());
  }

}
