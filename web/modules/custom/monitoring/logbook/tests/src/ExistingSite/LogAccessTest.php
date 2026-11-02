<?php

namespace Drupal\Tests\logbook\ExistingSite;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogAccessControlHandler;
use Drupal\logbook\LogInterface;
use Drupal\projects\ProjectInterface;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the log entity access with the site roles and log patterns.
 */
#[CoversClass(LogAccessControlHandler::class)]
#[Group('logbook')]
class LogAccessTest extends ProjectResourceTestBase {

  /**
   * Tests that only administrators view logs without any visibility.
   */
  public function testHiddenLog(): void {
    // The pattern feedback_create is neither public, detectable nor
    // observable.
    $log = $this->createLog('feedback_create');

    $this->assertTrue($log->access('view', $this->createCreative('administrator')));
    $this->assertFalse($log->access('view', $this->createSupervisor()));
    $this->assertFalse($log->access('view', $this->createManager()));

    $result = $log->access('view', $this->createCreative(), TRUE);
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('user.permissions', $result->getCacheContexts());
  }

  /**
   * Tests the access to a detectable and observable log of a project.
   */
  public function testObservableProjectLog(): void {
    // The pattern project_submit is detectable and observable, not public.
    $project = $this->createProject();
    $log = $this->createLog('project_submit', $project);

    $this->assertTrue($log->access('view', $project->getOwner()->getManager()));
    $this->assertTrue($log->access('view', $this->createSupervisor()));
    $this->assertTrue($log->access('view', $this->createCreative('observer')));
    $this->assertFalse($log->access('view', $this->createManager()));
    $this->assertFalse($log->access('view', $this->createCreative()));
  }

  /**
   * Creates a log of a pattern, optionally for a project.
   */
  protected function createLog(string $type, ?ProjectInterface $project = NULL): LogInterface {
    $values = ['type' => $type, 'author' => $this->createCreative()->id()];
    if ($project) {
      $values['project'] = $project->id();
    }
    $log = Log::create($values);
    $log->save();
    $this->markEntityForCleanup($log);
    return $log;
  }

}
