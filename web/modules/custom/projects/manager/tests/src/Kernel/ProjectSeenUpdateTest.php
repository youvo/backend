<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Core\Database\Statement\FetchAs;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;

/**
 * Tests the update that seeds the seen projects.
 *
 * @group manager
 */
class ProjectSeenUpdateTest extends MailerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'user_types',
    'views',
    'logbook',
    'manager',
  ];

  /**
   * Existing manager and project pairs are marked as seen, only unfinished.
   */
  public function testUpdateSeedsExistingPairs(): void {
    $this->installEntitySchema('log');
    // The table does not exist yet, as on a site before the update.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();
    $completed = $this->createProject(ProjectState::Completed);
    $completed->save();

    $this->container->get('module_handler')->loadInclude('manager', 'install');
    manager_update_11001();
    // Running it again changes nothing.
    manager_update_11001();

    $rows = $this->container->get('database')->select('manager_project_seen', 's')
      ->fields('s', ['uid', 'project_id'])
      ->execute()
      ->fetchAll(FetchAs::Associative);
    $this->assertSame([['uid' => (string) $manager->id(), 'project_id' => (string) $project->id()]], $rows);
  }

}
