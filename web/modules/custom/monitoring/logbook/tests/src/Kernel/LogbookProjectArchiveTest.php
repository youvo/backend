<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\EventSubscriber\LogbookProjectArchiveSubscriber;
use Drupal\logbook\EventSubscriber\LogbookProjectUnarchiveSubscriber;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\Event\ProjectUnarchiveEvent;
use Drupal\projects\ProjectArchiveReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the logs of the project archive and unarchive events.
 */
#[CoversClass(LogbookProjectArchiveSubscriber::class)]
#[CoversClass(LogbookProjectUnarchiveSubscriber::class)]
#[Group('logbook')]
class LogbookProjectArchiveTest extends LogbookKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['project_archive', 'project_unarchive'] as $id) {
      $values = Yaml::decode(file_get_contents(__DIR__ . '/../../../config/install/logbook.log_pattern.' . $id . '.yml'));
      $this->createPattern($id, array_diff_key($values, ['tokens' => 1]));
    }
    $this->createPattern('project_reset');
    $this->createPattern('project_apply');
  }

  /**
   * Gets the archive status of logs by id.
   *
   * @return array<int, bool>
   *   Whether each log is archived.
   */
  protected function archivedById(): array {
    $logs = Log::loadMultiple();
    return array_map(static fn (Log $log) => $log->isArchived(), $logs);
  }

  /**
   * Gets the logs of a pattern.
   *
   * @return \Drupal\logbook\Entity\Log[]
   *   The logs.
   */
  protected function logsOfType(string $type): array {
    return array_filter(Log::loadMultiple(), static fn (Log $log) => $log->bundle() === $type);
  }

  /**
   * Archive archives the logs; unarchive re-activates them from the reset on.
   */
  public function testArchiveAndUnarchiveLogs(): void {

    $project = $this->createLogbookProject();
    $before_reset = $this->createLog('project_apply', ['project' => $project->id()])->setArchived();
    $before_reset->save();
    $reset = $this->createLog('project_reset', ['project' => $project->id()]);
    $after_reset = $this->createLog('project_apply', ['project' => $project->id()]);
    $other = $this->createLog('project_apply', ['project' => $this->createLogbookProject('Other')->id()]);

    $event = (new ProjectArchiveEvent($project))->setReason(ProjectArchiveReason::Unattractive)
      ->setMail('Archiviert', 'Das Projekt wird archiviert.');
    $this->eventDispatcher->dispatch($event);

    $archive_logs = $this->logsOfType('project_archive');
    $this->assertCount(1, $archive_logs);
    $archive_log = reset($archive_logs);
    $this->assertSame(['reason' => 'unattractive'], $archive_log->getMisc());
    $this->assertSame('Das Projekt wird archiviert.', $archive_log->getMessage());
    $this->assertTrue($archive_log->getPattern()->isDetectable());
    $this->assertFalse($archive_log->getPattern()->isPublic());
    $archived = $this->archivedById();
    $this->assertTrue($archived[$before_reset->id()]);
    $this->assertTrue($archived[$reset->id()]);
    $this->assertTrue($archived[$after_reset->id()]);
    $this->assertFalse($archived[$archive_log->id()]);
    $this->assertFalse($archived[$other->id()]);

    $this->eventDispatcher->dispatch(new ProjectUnarchiveEvent($project));

    $archived = $this->archivedById();
    $this->assertTrue($archived[$before_reset->id()]);
    $this->assertFalse($archived[$reset->id()]);
    $this->assertFalse($archived[$after_reset->id()]);
    $this->assertFalse($archived[$archive_log->id()]);
    $this->assertCount(1, $this->logsOfType('project_unarchive'));
  }

}
