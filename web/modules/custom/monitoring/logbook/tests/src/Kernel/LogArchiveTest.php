<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\EventSubscriber\LogbookProjectResetSubscriber;
use Drupal\logbook\LogAccessControlHandler;
use Drupal\projects\Event\ProjectResetEvent;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests archiving logs on reset and the access to archived logs.
 */
#[CoversClass(LogbookProjectResetSubscriber::class)]
#[CoversClass(LogAccessControlHandler::class)]
#[Group('logbook')]
class LogArchiveTest extends LogbookKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createPattern('project_apply', ['detectable' => TRUE, 'observable' => TRUE]);
    $this->createPattern('project_reset', ['detectable' => TRUE, 'observable' => TRUE]);
    // User 1 bypasses permission checks: use it up.
    $this->createUser();
  }

  /**
   * Reset archives the earlier logs of the project, not its own log.
   */
  public function testResetArchivesEarlierLogs(): void {
    $project = $this->createLogbookProject();
    $earlier = $this->createLog('project_apply', ['project' => $project->id()]);
    $other = $this->createLog('project_apply', ['project' => $this->createLogbookProject('Other')->id()]);

    $this->eventDispatcher->dispatch(new ProjectResetEvent($project));

    $this->assertTrue(Log::load($earlier->id())->isArchived());
    $this->assertFalse(Log::load($other->id())->isArchived());
    $reset_logs = Log::loadMultiple(
      $this->container->get('entity_type.manager')->getStorage('log')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'project_reset')
        ->execute()
    );
    $this->assertCount(1, $reset_logs);
    $this->assertFalse(reset($reset_logs)->isArchived());
  }

  /**
   * Archived logs are only accessible with the permission.
   */
  public function testArchivedLogAccess(): void {
    $log = $this->createLog('project_apply')->setArchived();
    $log->save();
    $observer = $this->createUser(['view detectable log']);
    $supervisor = $this->createUser(['view detectable log', 'view archived log']);

    $result = $log->access('view', $observer, TRUE);
    $this->assertTrue($result->isForbidden());
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('user.permissions', $result->getCacheContexts());
    $this->assertContains('log:' . $log->id(), $result->getCacheTags());
    $this->assertTrue($log->access('view', $supervisor));

    // Active again, the log follows the pattern flags.
    $log->setArchived(FALSE)->save();
    $this->container->get('entity_type.manager')->getAccessControlHandler('log')->resetCache();
    $this->assertTrue($log->access('view', $observer));
  }

  /**
   * Access-checked queries leave out archived logs without the permission.
   */
  public function testQueryAccess(): void {
    $active = $this->createLog('project_apply');
    $archived = $this->createLog('project_apply')->setArchived();
    $archived->save();
    $query = fn () => array_map('intval', array_values(
      $this->container->get('entity_type.manager')->getStorage('log')->getQuery()->accessCheck(TRUE)->sort('id')->execute()
    ));

    $this->setCurrentUser($this->createUser(['view detectable log']));
    $this->assertSame([(int) $active->id()], $query());

    $this->setCurrentUser($this->createUser(['view detectable log', 'view archived log']));
    $this->assertSame([(int) $active->id(), (int) $archived->id()], $query());
  }

}
