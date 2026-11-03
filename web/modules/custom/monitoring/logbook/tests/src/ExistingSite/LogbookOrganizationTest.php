<?php

namespace Drupal\Tests\logbook\ExistingSite;

use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the logbook of an organization on its profile.
 */
#[Group('logbook')]
class LogbookOrganizationTest extends ProjectResourceTestBase {

  /**
   * The display lists the logs of the organization and of its projects.
   */
  public function testOrganizationLogs(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $this->assertInstanceOf(Organization::class, $organization);
    $other = $this->createOrganization();

    $submit = $this->createLog('project_submit', ['organization' => $organization->id(), 'project' => $project->id()], 100);
    // A log of the project without the organization, as feedback logs are.
    $publish = $this->createLog('project_publish', ['project' => $project->id()], 200);
    $manage = $this->createLog('organization_manage', ['organization' => $organization->id()], 300);
    // Left out: another organization's log and an unobservable pattern.
    $this->createLog('organization_manage', ['organization' => $other->id()], 400);
    $this->createLog('feedback_create', ['organization' => $organization->id()], 500);

    $view = $this->executeView($organization, 0);
    $this->assertSame([(int) $manage->id(), (int) $publish->id(), (int) $submit->id()], $this->ids($view));
  }

  /**
   * The display shows five logs, and ten more with each click.
   */
  public function testLoadMore(): void {

    $organization = $this->createOrganization();
    $ids = [];
    foreach (range(1, 16) as $i) {
      $ids[] = (int) $this->createLog('organization_manage', ['organization' => $organization->id()], $i)->id();
    }
    $newest = array_reverse($ids);

    $view = $this->executeView($organization, 0);
    $this->assertSame(array_slice($newest, 0, 5), $this->ids($view));
    $this->assertSame('1', $this->nextPage($view));

    // The second run reads from the cache, which must keep the button.
    foreach ([1, 2] as $run) {
      $view = $this->executeView($organization, 1);
      $this->assertSame(array_slice($newest, 0, 15), $this->ids($view), "Run $run");
      $this->assertSame('2', $this->nextPage($view), "Run $run");
    }

    $view = $this->executeView($organization, 2);
    $this->assertSame($newest, $this->ids($view));
    $this->assertNull($this->nextPage($view));
  }

  /**
   * Executes the organization display of the logbook on a page.
   */
  protected function executeView(Organization $organization, int $page): ViewExecutable {
    $view = Views::getView('logbook');
    $this->assertInstanceOf(ViewExecutable::class, $view);
    $view->setDisplay('organization');
    $view->setArguments([$organization->id()]);
    $view->setCurrentPage($page);
    $view->execute();
    return $view;
  }

  /**
   * Gets the ids of the listed logs.
   *
   * @return int[]
   *   The log ids, as listed.
   */
  protected function ids(ViewExecutable $view): array {
    return array_map(static fn ($row): int => (int) $row->_entity->id(), $view->result);
  }

  /**
   * Gets the page the load more button opens, if there is one.
   */
  protected function nextPage(ViewExecutable $view): ?string {
    $build = $view->pager->render([]);
    if (!$build) {
      return NULL;
    }
    return $build['#items'][0]['#url']->getOption('query')['page'];
  }

  /**
   * Creates a log of a pattern, some seconds after an old fixed time.
   *
   * The fixed time gives the logs a known order, older than the site's logs.
   */
  protected function createLog(string $type, array $values, int $seconds): LogInterface {
    $log = Log::create($values + [
      'type' => $type,
      'author' => $this->createCreative()->id(),
      'created' => 1000000000 + $seconds,
    ]);
    $log->save();
    $this->markEntityForCleanup($log);
    return $log;
  }

}
