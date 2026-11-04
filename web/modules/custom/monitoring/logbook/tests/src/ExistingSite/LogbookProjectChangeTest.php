<?php

namespace Drupal\Tests\logbook\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\MailInterceptTrait;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the log of content changes of projects.
 *
 * Doesn't extend ProjectResourceTestBase, which removes the logbook listeners.
 */
#[Group('logbook')]
class LogbookProjectChangeTest extends ExistingSiteBase {

  use MailInterceptTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->interceptMails();
  }

  /**
   * Creates a project of an organization with a manager.
   */
  protected function createProject(ProjectState $state): ProjectInterface {

    $manager = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);

    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.org',
      'field_manager' => $manager,
      'status' => 1,
    ]);
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);

    $project = Project::create([
      'uid' => $organization->id(),
      'title' => $this->randomMachineName(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());

    return $project;
  }

  /**
   * Gets the project change logs of a project.
   *
   * @return \Drupal\logbook\LogInterface[]
   *   The logs.
   */
  protected function changeLogs(ProjectInterface $project): array {
    $ids = $this->container->get('entity_type.manager')->getStorage('log')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'project_change')
      ->condition('project', $project->id())
      ->execute();
    $logs = Log::loadMultiple($ids);
    array_walk($logs, fn (LogInterface $log) => $this->markEntityForCleanup($log));
    return array_values($logs);
  }

  /**
   * Tests that a content change after submit writes a log.
   */
  public function testEditAfterSubmitLogs(): void {

    $project = $this->createProject(ProjectState::Pending);
    $organization = $project->getOwner();
    $previous_revision_id = (int) $project->getRevisionId();
    $this->setCurrentUser($organization);

    $project->setTitle('Changed')->set('field_city', 'Hamburg')->save();

    $logs = $this->changeLogs($project);
    $this->assertCount(1, $logs);
    $log = $logs[0];
    $this->assertSame((int) $organization->id(), (int) $log->getOwnerId());
    $this->assertSame((int) $organization->id(), (int) $log->getOrganization()?->id());
    $this->assertSame((int) $organization->getManager()->id(), (int) $log->getManager()?->id());
    $this->assertSame([
      'fields' => ['title', 'field_city'],
      'previous_revision_id' => $previous_revision_id,
      'revision_id' => (int) $project->getRevisionId(),
    ], $log->getMisc());

    $markup = (string) $log->get('markup')->value;
    $this->assertStringContainsString('bearbeitet: Project Title, City.', $markup);
    $this->assertStringContainsString('/projects/' . $project->id() . '/revisions/' . $project->getRevisionId() . '/view', $markup);

    $processed = (string) $log->get('processed')->value;
    $this->assertStringNotContainsString('%Changes', $processed);
    $this->assertStringNotContainsString('%Diff', $processed);
    $this->assertStringNotContainsString('/revisions/', $processed);
  }

  /**
   * Tests that a manager's content change logs with the manager as author.
   */
  public function testManagerEditLogs(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();
    $this->setCurrentUser($manager);

    $project->setTitle('Changed')->save();

    $logs = $this->changeLogs($project);
    $this->assertCount(1, $logs);
    $this->assertSame((int) $manager->id(), (int) $logs[0]->getOwnerId());
    $this->assertStringContainsString('von der Managerin', (string) $logs[0]->get('markup')->value);
  }

  /**
   * Tests that a project owned by an administrator saves without a log.
   */
  public function testAdministratorOwnedProjectSavesWithoutLog(): void {

    $administrator = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $administrator->addRole('administrator');
    $administrator->save();
    $this->markEntityForCleanup($administrator);
    // Administrators may own projects, for example after changing the owner
    // in the admin form.
    $project = $this->createProject(ProjectState::Pending);
    $project->setOwnerId($administrator->id())->setTitle('Changed')->save();

    $this->assertSame('Changed', $this->container->get('entity_type.manager')->getStorage('project')->loadUnchanged($project->id())->getTitle());
    $this->assertSame([], $this->changeLogs($project));
  }

  /**
   * Tests that draft edits and workflow saves don't log.
   */
  public function testDraftAndWorkflowSavesDontLog(): void {

    $draft = $this->createProject(ProjectState::Draft);
    $draft->setTitle('Changed')->save();
    $this->assertSame([], $this->changeLogs($draft));

    $pending = $this->createProject(ProjectState::Pending);
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $creative->addRole('creative');
    $creative->save();
    $this->markEntityForCleanup($creative);
    $pending->appendApplicant($creative)->save();
    $this->assertSame([], $this->changeLogs($pending));
  }

}
