<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\mailer\Manager\ManagerMailDefinition;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which manager mails are offered for which project state.
 */
#[CoversClass(ManagerMailRegistry::class)]
#[Group('mailer')]
class ManagerMailRegistryTest extends UnitTestCase {

  /**
   * The registry under test.
   */
  protected ManagerMailRegistry $registry;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The labels of the decline and archive reasons are translated.
    $container = new ContainerBuilder();
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
    $this->registry = new ManagerMailRegistry();
  }

  /**
   * Creates a project mock in a state.
   */
  protected function project(ProjectState $state, bool $applicant = FALSE, bool $can_decline = FALSE, bool $archived = FALSE): ProjectInterface {
    $lifecycle = $this->createMock(ProjectLifecycleInterface::class);
    $lifecycle->method('isDraft')->willReturn($state === ProjectState::Draft);
    $lifecycle->method('isPending')->willReturn($state === ProjectState::Pending);
    $lifecycle->method('isOpen')->willReturn($state === ProjectState::Open);
    $lifecycle->method('isOngoing')->willReturn($state === ProjectState::Ongoing);
    $lifecycle->method('isCompleted')->willReturn($state === ProjectState::Completed);
    $lifecycle->method('isDeclined')->willReturn($state === ProjectState::Declined);
    $lifecycle->method('canDecline')->willReturn($can_decline);
    $project = $this->createMock(ProjectInterface::class);
    $project->method('lifecycle')->willReturn($lifecycle);
    $project->method('hasApplicant')->willReturn($applicant);
    $project->method('isArchived')->willReturn($archived);
    return $project;
  }

  /**
   * Gets the subtypes of definitions.
   *
   * @param \Drupal\mailer\Manager\ManagerMailDefinition[] $definitions
   *   The definitions.
   *
   * @return string[]
   *   The subtypes.
   */
  protected function subtypes(array $definitions): array {
    return array_map(static fn (ManagerMailDefinition $d) => $d->subtype, $definitions);
  }

  /**
   * Each state offers its reminder; open the variant matching the applicants.
   */
  public function testReminders(): void {
    $this->assertSame(['reminder_draft'], $this->subtypes($this->registry->available($this->project(ProjectState::Draft))));
    $this->assertSame(['reminder_open_noone'], $this->subtypes($this->registry->available($this->project(ProjectState::Open))));
    $this->assertSame(['reminder_open'], $this->subtypes($this->registry->available($this->project(ProjectState::Open, TRUE))));
    $this->assertSame(['reminder_ongoing'], $this->subtypes($this->registry->available($this->project(ProjectState::Ongoing))));
    foreach ([ProjectState::Pending, ProjectState::Completed, ProjectState::Declined] as $state) {
      $this->assertSame([], $this->registry->available($this->project($state)), $state->value);
    }
    $this->assertNotNull($this->registry->find($this->project(ProjectState::Draft), 'reminder_draft'));
    $this->assertNull($this->registry->find($this->project(ProjectState::Draft), 'reminder_ongoing'));
  }

  /**
   * Decline mails are offered when the lifecycle allows declining.
   *
   * They are not part of the reminders, and their subtype maps to the reason.
   */
  public function testDeclines(): void {

    $declinable = $this->project(ProjectState::Draft, can_decline: TRUE);
    $declines = $this->registry->availableDeclines($declinable);
    $this->assertSame(array_map(ManagerMailRegistry::declineSubtype(...), ProjectDeclineReason::cases()), $this->subtypes($declines));
    $this->assertSame('No specific reason', $declines[0]->label);
    $this->assertNotNull($this->registry->find($declinable, 'decline_deadline'));
    $this->assertSame(['reminder_draft'], $this->subtypes($this->registry->available($declinable)));

    $this->assertSame([], $this->registry->availableDeclines($this->project(ProjectState::Draft)));
    $this->assertNull($this->registry->find($this->project(ProjectState::Pending), 'decline_deadline'));

    foreach (ProjectDeclineReason::cases() as $reason) {
      $this->assertSame($reason, ManagerMailRegistry::declineReason(ManagerMailRegistry::declineSubtype($reason)));
    }
    $this->assertNull(ManagerMailRegistry::declineReason('reminder_draft'));
  }

  /**
   * Archive mails are offered for active open, ongoing and completed projects.
   *
   * They are not part of the reminders, and their subtype maps to the reason.
   */
  public function testArchives(): void {

    foreach (ProjectState::cases() as $state) {
      $project = $this->project($state);
      $offered = in_array($state, ManagerMailRegistry::ARCHIVE_MAIL_STATES, TRUE);
      $this->assertCount($offered ? count(ProjectArchiveReason::cases()) : 0, $this->registry->availableArchives($project), $state->value);
      $this->assertSame($offered, $this->registry->find($project, 'archive_external') !== NULL, $state->value);
      $this->assertNotContains('archive_custom', $this->subtypes($this->registry->available($project)));
    }
    $this->assertSame([], $this->registry->availableArchives($this->project(ProjectState::Open, archived: TRUE)));

    foreach (ProjectArchiveReason::cases() as $reason) {
      $this->assertSame($reason, ManagerMailRegistry::archiveReason(ManagerMailRegistry::archiveSubtype($reason)));
    }
    $this->assertNull(ManagerMailRegistry::archiveReason('decline_custom'));
  }

}
