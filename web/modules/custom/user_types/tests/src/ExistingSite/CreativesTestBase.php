<?php

namespace Drupal\Tests\user_types\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Provides a base for the creatives finder tests on the installed site.
 */
abstract class CreativesTestBase extends ExistingSiteBase {

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
   * Creates an organization managed by a new manager.
   */
  protected function createOrganization(): Organization {
    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'field_contact' => 'Olga Org',
      'mail' => $this->randomMachineName() . '@example.org',
      'field_manager' => $this->createManager(),
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole(Organization::ROLE_ORGANIZATION);
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

  /**
   * Creates a published project of a new organization.
   */
  protected function createProject(ProjectState $state = ProjectState::Open): ProjectInterface {
    $project = Project::create([
      'type' => 'project',
      'uid' => $this->createOrganization()->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $this->randomMachineName(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());
    $this->projectIds[] = $project->id();
    return $project;
  }

}
