<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\EventSubscriber\LogbookProjectHideSubscriber;
use Drupal\logbook\EventSubscriber\LogbookProjectShowSubscriber;
use Drupal\projects\Event\ProjectHideEvent;
use Drupal\projects\Event\ProjectShowEvent;
use Drupal\projects\Exception\ProjectActionException;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the logs of hiding and showing a project.
 */
#[CoversClass(LogbookProjectHideSubscriber::class)]
#[CoversClass(LogbookProjectShowSubscriber::class)]
#[Group('logbook')]
class LogbookProjectHideShowTest extends LogbookKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['project_hide', 'project_show'] as $id) {
      $values = Yaml::decode(file_get_contents(__DIR__ . "/../../../config/install/logbook.log_pattern.$id.yml"));
      $this->createPattern($id, array_diff_key($values, ['tokens' => 1]));
    }
  }

  /**
   * Hide and show are logged as detectable only logs of the project.
   */
  public function testHideAndShowAreLogged(): void {
    $project = $this->createProject(ProjectState::Open);
    $project->save();
    $this->eventDispatcher->dispatch(new ProjectHideEvent($project));
    $this->eventDispatcher->dispatch(new ProjectShowEvent($project));

    $logs = array_values(Log::loadMultiple());
    $this->assertSame(['project_hide', 'project_show'], array_map(static fn (Log $log) => $log->bundle(), $logs));
    foreach ($logs as $log) {
      $this->assertSame((int) $project->id(), (int) $log->getProject()->id());
      $this->assertTrue($log->getPattern()->isDetectable());
      $this->assertFalse($log->getPattern()->isObservable());
      $this->assertFalse($log->getPattern()->isPublic());
    }
  }

  /**
   * A refused hide writes no log.
   */
  public function testRefusedHideIsNotLogged(): void {
    $project = $this->createProject(ProjectState::Draft);
    $project->save();
    try {
      $this->eventDispatcher->dispatch(new ProjectHideEvent($project));
    }
    catch (ProjectActionException) {
      // Expected: drafts can not be hidden.
    }
    $this->assertSame([], Log::loadMultiple());
  }

}
