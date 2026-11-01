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
   * Tests that supervisors and observers view decline logs, managers do not.
   */
  public function testDeclineLog(): void {
    // The pattern project_decline is detectable only.
    $project = $this->createProject();
    $log = $this->createLog('project_decline', $project);

    $this->assertTrue($log->access('view', $this->createSupervisor()));
    $this->assertTrue($log->access('view', $this->createCreative('observer')));
    $this->assertFalse($log->access('view', $project->getOwner()->getManager()));
    $this->assertFalse($log->access('view', $project->getOwner()));
  }

  /**
   * Tests that reset logs are visible beyond the manager of the organization.
   */
  public function testResetLog(): void {
    // The pattern project_reset is detectable and observable.
    $project = $this->createProject();
    $log = $this->createLog('project_reset', $project);

    $this->assertTrue($log->access('view', $project->getOwner()->getManager()));
    $this->assertTrue($log->access('view', $this->createSupervisor()));
    $this->assertTrue($log->access('view', $this->createCreative('observer')));
    $this->assertFalse($log->access('view', $this->createManager()));
  }

  /**
   * Tests that only supervisors view archived logs.
   */
  public function testArchivedLog(): void {
    $project = $this->createProject();
    $log = $this->createLog('project_submit', $project)->setArchived();
    $log->save();

    $this->assertTrue($log->access('view', $this->createSupervisor()));
    $this->assertFalse($log->access('view', $this->createCreative('observer')));
    $this->assertFalse($log->access('view', $project->getOwner()->getManager()));
  }

  /**
   * Tests that public logs of hidden projects are not public.
   */
  public function testHiddenProjectLog(): void {
    // The pattern project_publish is public, detectable and observable.
    $project = $this->createProject();
    $log = $this->createLog('project_publish', $project);
    $creative = $this->createCreative();
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('log');
    $this->assertTrue($log->access('view', $creative));

    $project->setHidden()->save();
    $handler->resetCache();
    $result = $log->access('view', $creative, TRUE);
    $this->assertFalse($result->isAllowed());
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('project:' . $project->id(), $result->getCacheTags());
    $this->assertTrue($log->access('view', $project->getOwner()->getManager()));
    $this->assertTrue($log->access('view', $this->createCreative('observer')));
    $this->assertTrue($log->access('view', $this->createSupervisor()));

    $project->setHidden(FALSE)->save();
    $handler->resetCache();
    $result = $log->access('view', $creative, TRUE);
    $this->assertTrue($result->isAllowed());
    $this->assertInstanceOf(CacheableDependencyInterface::class, $result);
    $this->assertContains('project:' . $project->id(), $result->getCacheTags());
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
