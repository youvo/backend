<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Provides a base for the stats tests on the installed site.
 */
abstract class StatsTestBase extends ExistingSiteBase {

  /**
   * The ids of the created projects.
   *
   * @var array<int, int|string>
   */
  protected array $projectIds = [];

  /**
   * Deletes the logs of the created projects, which are no children of them.
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
   * Gets the site timezone.
   */
  protected function timezone(): \DateTimeZone {
    return new \DateTimeZone($this->container->get('config.factory')->get('system.date')->get('timezone.default'));
  }

  /**
   * Gets the timestamp of 12:00 local on a Y-m-d date.
   */
  protected function noon(string $date): int {
    return (new \DateTimeImmutable($date . ' 12:00:00', $this->timezone()))->getTimestamp();
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
   * Creates an organization with a role of the site.
   */
  protected function createOrganizationUser(string $role): Organization {
    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'field_contact' => 'Olga Org',
      'mail' => $this->randomMachineName() . '@example.org',
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole($role);
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

  /**
   * Creates a creative with the manager role.
   */
  protected function createManager(): Creative {
    return $this->createCreative('creative', 'manager');
  }

  /**
   * Creates an organization managed by a manager, created on a Y-m-d date.
   */
  protected function createOrganization(Creative $manager, string $created): Organization {
    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'field_contact' => 'Olga Org',
      'mail' => $this->randomMachineName() . '@example.org',
      'field_manager' => $manager,
      'pass' => 'password',
      'status' => 1,
      'created' => $this->noon($created),
    ]);
    $organization->addRole(Organization::ROLE_ORGANIZATION);
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

  /**
   * Creates a project of an organization and applies lifecycle transitions.
   *
   * @param \Drupal\organizations\Entity\Organization $organization
   *   The owner.
   * @param array<int, array{0: string, 1: string}> $steps
   *   The transitions with their Y-m-d dates, applied at 12:00 local.
   */
  protected function createProjectWithHistory(Organization $organization, array $steps): ProjectInterface {

    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $this->randomMachineName(),
      'field_lifecycle' => ProjectState::Draft->value,
      'created' => $steps ? $this->noon($steps[0][1]) : NULL,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());
    $this->projectIds[] = $project->id();

    foreach ($steps as [$transition, $date]) {
      if ($transition === 'mediate' || $transition === 'complete') {
        $project->appendParticipant($this->createCreative());
      }
      $this->assertTrue($project->lifecycle()->{$transition}($this->noon($date)), $transition . ' on ' . $date);
    }
    $project->save();

    return $project;
  }

  /**
   * Archives a project with a reason on a Y-m-d date.
   */
  protected function archive(ProjectInterface $project, string $reason, string $date): void {
    $this->createLog('project_archive', $date, [
      'project' => $project,
      'misc' => ['reason' => $reason],
    ]);
    $project->setArchived();
    $project->save();
  }

  /**
   * Creates a log at 12:00 local on a Y-m-d date.
   *
   * @param string $type
   *   The log pattern.
   * @param string $date
   *   The date.
   * @param array{project?: \Drupal\projects\ProjectInterface, creatives?: array<int, \Drupal\Core\Session\AccountInterface>, misc?: array<string, mixed>} $values
   *   The project, the creatives and the misc data.
   */
  protected function createLog(string $type, string $date, array $values): LogInterface {

    $log = Log::create(['type' => $type]);
    $log->setCreatedTime($this->noon($date));
    if (isset($values['project'])) {
      $project = $values['project'];
      $log->setProject($project);
      $organization = $project->getOwner();
      $log->setOrganization($organization);
      if ($manager = $organization->getManager()) {
        $log->setManager($manager);
      }
    }
    if (isset($values['creatives'])) {
      $log->setCreatives($values['creatives']);
    }
    if (isset($values['misc'])) {
      $log->setMisc($values['misc']);
    }
    $log->save();
    $this->markEntityForCleanup($log);

    return $log;
  }

  /**
   * Builds the 2001 scenario of three organizations of one manager.
   */
  protected function setUpScenario(): void {

    $manager = $this->createManager();

    $o1 = $this->createOrganization($manager, '2001-01-01');
    // A.
    $this->createProjectWithHistory($o1, [
      ['submit', '2001-01-10'],
      ['publish', '2001-01-15'],
      ['mediate', '2001-02-01'],
      ['complete', '2001-03-01'],
    ]);
    // B.
    $this->createProjectWithHistory($o1, [
      ['submit', '2001-01-20'],
      ['publish', '2001-02-10'],
      ['mediate', '2001-02-20'],
    ]);
    // C.
    $this->createProjectWithHistory($o1, [
      ['submit', '2001-03-01'],
      ['publish', '2001-03-05'],
    ]);

    $o2 = $this->createOrganization($manager, '2001-03-01');
    $d = $this->createProjectWithHistory($o2, [
      ['submit', '2001-03-10'],
      ['publish', '2001-03-12'],
    ]);
    $this->archive($d, 'external', '2001-04-01');
    // E.
    $this->createProjectWithHistory($o2, [
      ['submit', '2001-04-01'],
      ['publish', '2001-04-02'],
      ['mediate', '2001-04-10'],
      ['reset', '2001-05-01'],
      ['submit', '2001-05-05'],
      ['publish', '2001-05-10'],
    ]);

    $o3 = $this->createOrganization($manager, '2000-06-01');
    // F.
    $this->createProjectWithHistory($o3, [
      ['submit', '2000-12-20'],
      ['publish', '2000-12-28'],
      ['mediate', '2001-01-05'],
    ]);
    // G, declined from draft, which needs the prospect role.
    $o3->addRole(Organization::ROLE_PROSPECT);
    $o3->save();
    $this->createProjectWithHistory($o3, [
      ['decline', '2001-06-05'],
    ]);
    $o3->removeRole(Organization::ROLE_PROSPECT);
    $o3->save();
    $h = $this->createProjectWithHistory($o3, [
      ['submit', '2001-07-01'],
      ['publish', '2001-07-05'],
    ]);
    $this->archive($h, 'unattractive', '2001-08-01');
  }

}
