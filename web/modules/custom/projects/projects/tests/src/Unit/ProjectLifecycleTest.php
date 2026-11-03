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
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Argument;

/**
 * Tests the project lifecycle.
 */
#[CoversClass(ProjectLifecycle::class)]
#[Group('projects')]
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
   */
  #[DataProvider('isStateProvider')]
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
      $cases[$state->value] = ['state' => $state];
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
    $project->isArchived()->willReturn(FALSE);
    $this->lifecycle->setProject($project->reveal());
  }

  /**
   * Tests that a draft project can only be declined for prospect owners.
   */
  public function testDeclineDraftRequiresProspect(): void {
    $states = [ProjectState::Draft];
    $this->prophesizeWorkflow($states, [TRUE], FALSE, FALSE);
    $this->expectException(LifecycleTransitionException::class);
    $this->lifecycle->decline();
  }

  /**
   * Tests whether a project can be declined.
   */
  #[DataProvider('canDeclineProvider')]
  public function testCanDecline(ProjectState $state, bool $has_transition, bool $is_prospect, bool $expected): void {
    [$project, $history] = $this->prophesizeWorkflow([$state], [$has_transition], FALSE, $is_prospect);
    $this->assertSame($expected, $this->lifecycle->canDecline());
    // The check neither changes the state nor writes the history.
    $project->set(Argument::any(), Argument::any())->shouldNotHaveBeenCalled();
    $history->appendItem(Argument::any())->shouldNotHaveBeenCalled();
  }

  /**
   * Provides data for the testCanDecline method.
   */
  public static function canDeclineProvider(): array {
    return [
      'draft of prospect' => [ProjectState::Draft, TRUE, TRUE, TRUE],
      'draft of organization' => [ProjectState::Draft, TRUE, FALSE, FALSE],
      'pending' => [ProjectState::Pending, TRUE, FALSE, TRUE],
      'open' => [ProjectState::Open, FALSE, TRUE, FALSE],
      'declined' => [ProjectState::Declined, FALSE, TRUE, FALSE],
    ];
  }

  /**
   * Prophesizes a project lifecycle with different workflow conditions.
   *
   * @return array{0: \Prophecy\Prophecy\ObjectProphecy<\Drupal\projects\ProjectInterface>, 1: \Prophecy\Prophecy\ObjectProphecy<\Drupal\Core\Field\FieldItemListInterface>}
   *   The prophecies of the project and of its lifecycle history.
   */
  protected function prophesizeWorkflow(array $states, array $has_transition, bool $has_participant, bool $is_prospect = TRUE): array {

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
    $project->isArchived()->willReturn(FALSE);
    $project->hasParticipant(Argument::is('Creative'))->willReturn($has_participant);
    $owner = $this->prophesize(Organization::class);
    $owner->hasRoleProspect()->willReturn($is_prospect);
    $project->getOwner()->willReturn($owner->reveal());

    $current_user = $this->prophesize(AccountProxyInterface::class);
    $time = $this->prophesize(TimeInterface::class);

    $this->lifecycle = new ProjectLifecycle(
      $current_user->reveal(),
      $entity_type_manager->reveal(),
      $time->reveal()
    );
    $this->lifecycle->setProject($project->reveal());
    return [$project, $history];
  }

}
