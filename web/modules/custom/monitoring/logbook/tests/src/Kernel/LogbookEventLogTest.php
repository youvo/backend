<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\creatives\Event\CreativeRegisterEvent;
use Drupal\feedback\Event\FeedbackCompleteEvent;
use Drupal\feedback\FeedbackInterface;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\Event\ProjectHideEvent;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\projects\Event\ProjectShowEvent;
use Drupal\projects\Event\ProjectUnarchiveEvent;
use Drupal\projects\Exception\ProjectActionException;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the logs that the project, creative and feedback events write.
 *
 * Dispatching these events on the site also sends mails, and the feedback
 * event takes a mocked feedback.
 */
#[Group('logbook')]
#[RunTestsInSeparateProcesses]
class LogbookEventLogTest extends LogbookKernelTestBase {

  /**
   * Creates a pattern from the config of the module.
   */
  protected function createInstalledPattern(string $id): void {
    $values = Yaml::decode(file_get_contents(__DIR__ . "/../../../config/install/logbook.log_pattern.$id.yml"));
    $this->createPattern($id, array_diff_key($values, ['tokens' => 1]));
  }

  /**
   * Gets the logs matching the conditions, by id.
   *
   * @return \Drupal\logbook\LogInterface[]
   *   The logs, keyed and sorted by id.
   */
  protected function logs(array $conditions = []): array {
    $query = $this->container->get('entity_type.manager')->getStorage('log')->getQuery()
      ->accessCheck(FALSE)
      ->sort('id');
    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }
    return Log::loadMultiple($query->execute());
  }

  /**
   * Gets the archive status of the logs of a project, by id.
   *
   * @return array<int, bool>
   *   Whether each log is archived.
   */
  protected function archivedById(ProjectInterface $project): array {
    return array_map(static fn (LogInterface $log) => $log->isArchived(), $this->logs(['project' => $project->id()]));
  }

  /**
   * Reset archives earlier logs; archive and unarchive follow the reset.
   */
  public function testResetArchiveAndUnarchiveLogs(): void {

    foreach (['project_archive', 'project_unarchive'] as $id) {
      $this->createInstalledPattern($id);
    }
    $this->createPattern('project_reset');
    $this->createPattern('project_apply');
    $project = $this->createLogbookProject();
    $other_project = $this->createLogbookProject('Other');
    $before_reset = $this->createLog('project_apply', ['project' => $project->id()]);
    $other = $this->createLog('project_apply', ['project' => $other_project->id()]);

    // Reset archives the earlier logs of the project, not its own log.
    $this->eventDispatcher->dispatch(new ProjectResetEvent($project));
    $reset_logs = $this->logs(['type' => 'project_reset']);
    $this->assertCount(1, $reset_logs);
    $reset = reset($reset_logs);
    $this->assertSame((int) $project->id(), (int) $reset->getProject()->id());
    $archived = $this->archivedById($project);
    $this->assertTrue($archived[$before_reset->id()]);
    $this->assertFalse($archived[$reset->id()]);
    $this->assertFalse($this->archivedById($other_project)[$other->id()]);

    // Archive archives all logs of the project, but not its own log.
    $after_reset = $this->createLog('project_apply', ['project' => $project->id()]);
    $event = (new ProjectArchiveEvent($project))->setReason(ProjectArchiveReason::Unattractive)
      ->setMail('Archiviert', 'Das Projekt wird archiviert.');
    $this->eventDispatcher->dispatch($event);
    $archive_logs = $this->logs(['type' => 'project_archive']);
    $this->assertCount(1, $archive_logs);
    $archive_log = reset($archive_logs);
    $this->assertSame(['reason' => 'unattractive'], $archive_log->getMisc());
    $this->assertSame("Archiviert\n\nDas Projekt wird archiviert.", $archive_log->getMessage());
    $this->assertTrue($archive_log->getPattern()->isDetectable());
    $this->assertFalse($archive_log->getPattern()->isPublic());
    $archived = $this->archivedById($project);
    $this->assertTrue($archived[$before_reset->id()]);
    $this->assertTrue($archived[$reset->id()]);
    $this->assertTrue($archived[$after_reset->id()]);
    $this->assertFalse($archived[$archive_log->id()]);
    $this->assertFalse($this->archivedById($other_project)[$other->id()]);

    // Unarchive re-activates the logs from the reset on.
    $this->eventDispatcher->dispatch(new ProjectUnarchiveEvent($project));
    $archived = $this->archivedById($project);
    $this->assertTrue($archived[$before_reset->id()]);
    $this->assertFalse($archived[$reset->id()]);
    $this->assertFalse($archived[$after_reset->id()]);
    $this->assertFalse($archived[$archive_log->id()]);
    $this->assertCount(1, $this->logs(['type' => 'project_unarchive']));
  }

  /**
   * Decline, hide and show are logged as detectable only; a refused hide not.
   */
  public function testDeclineHideAndShowLogs(): void {

    foreach (['project_decline', 'project_hide', 'project_show'] as $id) {
      $this->createInstalledPattern($id);
    }

    // The decline log keeps the reason and the mail text.
    $pending = $this->createProject(ProjectState::Pending);
    $pending->save();
    $event = new ProjectDeclineEvent($pending);
    $event->setReason(ProjectDeclineReason::Skill);
    $event->setMail('Absage', 'Leider passt das Projekt nicht.');
    $this->eventDispatcher->dispatch($event);
    $logs = $this->logs(['project' => $pending->id()]);
    $this->assertCount(1, $logs);
    $log = reset($logs);
    $this->assertSame('project_decline', $log->bundle());
    $this->assertSame(['reason' => 'skill'], $log->getMisc());
    $this->assertSame("Absage\n\nLeider passt das Projekt nicht.", $log->getMessage());

    // Hide and show are logged for the project.
    $open = $this->createProject(ProjectState::Open);
    $open->save();
    $this->eventDispatcher->dispatch(new ProjectHideEvent($open));
    $this->eventDispatcher->dispatch(new ProjectShowEvent($open));
    $hide_show = array_values($this->logs(['project' => $open->id()]));
    $this->assertSame(['project_hide', 'project_show'], array_map(static fn (LogInterface $log) => $log->bundle(), $hide_show));

    foreach ([$log, ...$hide_show] as $log) {
      $this->assertTrue($log->getPattern()->isDetectable(), $log->bundle());
      $this->assertFalse($log->getPattern()->isObservable(), $log->bundle());
      $this->assertFalse($log->getPattern()->isPublic(), $log->bundle());
    }

    // A refused hide writes no log.
    $draft = $this->createProject(ProjectState::Draft);
    $draft->save();
    try {
      $this->eventDispatcher->dispatch(new ProjectHideEvent($draft));
    }
    catch (ProjectActionException) {
      // Expected: drafts can not be hidden.
    }
    $this->assertSame([], $this->logs(['project' => $draft->id()]));
  }

  /**
   * The register and feedback logs take the time of their event.
   *
   * The dummy content logs registrations and feedback in the past.
   */
  public function testEventTimestamps(): void {

    $this->createPattern('creative_register');
    $this->createPattern('feedback_complete');

    // A creative registered at a given time is logged at that time; without
    // a time, the log is written now.
    $creative = $this->createCreative();
    $this->eventDispatcher->dispatch(new CreativeRegisterEvent($creative, 'https://example.com', 1000));
    $this->eventDispatcher->dispatch(new CreativeRegisterEvent($creative, 'https://example.com'));
    $logs = array_values($this->logs(['type' => 'creative_register']));
    $this->assertCount(2, $logs);
    $this->assertSame(1000, (int) $logs[0]->get('created')->value);
    $this->assertGreaterThan(1000, (int) $logs[1]->get('created')->value);

    // A feedback completed at a given time is logged at that time.
    $project = $this->createLogbookProject();
    $feedback = $this->createMock(FeedbackInterface::class);
    $feedback->method('getProject')->willReturn($project);
    $feedback->method('id')->willReturn(7);
    $this->eventDispatcher->dispatch(new FeedbackCompleteEvent($feedback, 2000));
    $logs = $this->logs(['type' => 'feedback_complete']);
    $this->assertCount(1, $logs);
    $log = reset($logs);
    $this->assertSame(2000, (int) $log->get('created')->value);
    $this->assertSame(['feedback_id' => 7], $log->getMisc());
  }

}
