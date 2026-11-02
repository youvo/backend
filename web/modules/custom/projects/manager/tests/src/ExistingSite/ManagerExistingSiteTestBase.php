<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Entity\Log;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\user\UserInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Provides a base for the manager tests on the installed site.
 *
 * The accounts use the roles of the site. Saving a project writes a
 * project_create log, which the factory removes again so that every test
 * starts without logs. Logs are no children of their project: the logs of
 * the created projects are deleted after each test.
 */
abstract class ManagerExistingSiteTestBase extends ExistingSiteBase {

  /**
   * The ids of the created projects.
   *
   * @var array<int, int|string>
   */
  protected array $projectIds = [];

  /**
   * The creative that writes logs unless a test says otherwise.
   */
  protected ?Creative $logAuthor = NULL;

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->projectIds) {
      $storage = $this->container->get('entity_type.manager')->getStorage('log');
      $ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('project', $this->projectIds, 'IN')
        ->execute();
      if ($ids) {
        $storage->delete($storage->loadMultiple($ids));
      }
    }
    parent::tearDown();
  }

  /**
   * Creates a creative with roles of the site.
   */
  protected function createCreative(string ...$roles): Creative {
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    foreach ($roles ?: ['creative'] as $role) {
      $creative->addRole($role);
    }
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Creates a creative with the manager role.
   */
  protected function createManager(): Creative {
    return $this->createCreative('creative', 'manager');
  }

  /**
   * Creates a creative with the supervisor role.
   */
  protected function createSupervisor(): Creative {
    return $this->createCreative('creative', 'supervisor');
  }

  /**
   * Creates an organization, managed by a new manager unless one is given.
   */
  protected function createOrganization(?UserInterface $manager = NULL, string $role = Organization::ROLE_ORGANIZATION): Organization {
    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'field_contact' => 'Olga Org',
      'mail' => $this->randomMachineName() . '@example.org',
      'field_manager' => $manager ?? $this->createManager(),
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole($role);
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

  /**
   * Creates a published project of a new organization unless one is given.
   */
  protected function createProject(ProjectState $state = ProjectState::Open, ?Organization $organization = NULL, ?string $title = NULL): ProjectInterface {
    $organization ??= $this->createOrganization();
    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $title ?? $this->randomMachineName(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());
    $this->projectIds[] = $project->id();
    // Remove the project_create log of the save.
    $storage = $this->container->get('entity_type.manager')->getStorage('log');
    $storage->delete($storage->loadByProperties(['project' => $project->id()]));
    return $project;
  }

  /**
   * Gets the manager of the organization of a project.
   */
  protected function managerOf(ProjectInterface $project): Creative {
    $organization = $project->getOwner();
    $this->assertInstanceOf(Organization::class, $organization);
    $manager = $organization->getManager();
    $this->assertInstanceOf(Creative::class, $manager);
    return $manager;
  }

  /**
   * Creates a log of a project. The author defaults to another creative.
   */
  protected function createLog(ProjectInterface $project, string $type = 'project_apply', ?UserInterface $author = NULL, ?int $created = NULL): Log {
    $this->logAuthor ??= $this->createCreative();
    $log = Log::create([
      'type' => $type,
      'project' => $project->id(),
      'author' => ($author ?? $this->logAuthor)->id(),
    ] + ($created === NULL ? [] : ['created' => $created]));
    // The log is deleted with the logs of the project in tearDown().
    $log->save();
    return $log;
  }

  /**
   * Creates a note on a project; it goes with the project.
   */
  protected function createNote(ProjectInterface $project, string $subject = 'Anruf', string $body = 'Rückruf vereinbart', ?int $occurred = NULL, ?UserInterface $sender = NULL): CorrespondenceInterface {
    $note = Correspondence::create([
      'type' => 'note',
      'project' => $project->id(),
      'subject' => $subject,
      'body' => ['value' => $body, 'format' => 'plain_text'],
      'sender' => ($sender ?? $this->managerOf($project))->id(),
      'occurred' => $occurred ?? 86400 * 365,
    ]);
    $note->save();
    return $note;
  }

  /**
   * Creates a sent manager mail entry of a project; it goes with the project.
   */
  protected function createMailEntry(ProjectInterface $project, int $occurred = 2000, string $subject = 'M'): CorrespondenceInterface {
    $entry = Correspondence::create([
      'type' => 'mail',
      'project' => $project->id(),
      'subject' => $subject,
      'body' => ['value' => '<p>m</p>', 'format' => 'email_html'],
      'subtype' => 'mailer.manager.reminder_draft',
      'origin' => 'manager',
      'result' => 'sent',
      'occurred' => $occurred,
    ]);
    $entry->save();
    return $entry;
  }

  /**
   * Loads the correspondence of a project.
   *
   * @return \Drupal\correspondence\CorrespondenceInterface[]
   *   The entries, oldest first.
   */
  protected function entries(ProjectInterface $project): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('correspondence');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project->id())
      ->sort('id')
      ->execute();
    $storage->resetCache($ids);
    return array_values(array_filter($storage->loadMultiple($ids), static fn ($entry) => $entry instanceof CorrespondenceInterface));
  }

  /**
   * Loads the notes of a project.
   *
   * @return \Drupal\correspondence\CorrespondenceInterface[]
   *   The notes, oldest first.
   */
  protected function notes(ProjectInterface $project): array {
    return array_values(array_filter($this->entries($project), static fn (CorrespondenceInterface $entry) => $entry->isNote()));
  }

  /**
   * Reloads a project from storage.
   */
  protected function reloadProject(ProjectInterface $project): ProjectInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('project');
    $storage->resetCache([$project->id()]);
    $fresh = $storage->load($project->id());
    $this->assertInstanceOf(ProjectInterface::class, $fresh);
    return $fresh;
  }

  /**
   * Switches the current user.
   */
  protected function actAs(AccountInterface $account): void {
    $this->container->get('current_user')->setAccount($account);
  }

  /**
   * Deletes an entity that is marked for cleanup and unmarks it.
   */
  protected function deleteCreated(EntityInterface $entity): void {
    $entity->delete();
    $this->cleanupEntities = array_values(array_filter(
      $this->cleanupEntities,
      static fn (EntityInterface $marked) => $marked !== $entity,
    ));
  }

}
