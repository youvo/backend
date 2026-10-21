<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\logbook\Entity\Log;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectState;

/**
 * Tests the log of the project decline event.
 *
 * @group logbook
 */
class LogbookProjectDeclineTest extends LogbookKernelTestBase {

  /**
   * The decline log keeps the reason and is neither observable nor public.
   */
  public function testDeclineLog(): void {
    $values = Yaml::decode(file_get_contents(__DIR__ . '/../../../config/install/logbook.log_pattern.project_decline.yml'));
    $this->createPattern('project_decline', array_diff_key($values, ['tokens' => 1]));

    $project = $this->createProject(ProjectState::Pending);
    $project->save();
    $event = new ProjectDeclineEvent($project);
    $event->setReason(ProjectDeclineReason::Skill);
    $this->eventDispatcher->dispatch($event);

    $logs = Log::loadMultiple();
    $this->assertCount(1, $logs);
    $log = reset($logs);
    $this->assertSame('project_decline', $log->bundle());
    $this->assertSame(['reason' => 'skill'], $log->getMisc());
    $this->assertFalse($log->getPattern()->isObservable());
    $this->assertFalse($log->getPattern()->isPublic());
  }

}
