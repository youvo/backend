<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\Tests\youvo\Traits\RequestTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Provides a test base for project resources.
 */
abstract class ProjectResourceTestBase extends ExistingSiteBase {

  use RequestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();

    // Only keep the listeners of the projects module for project events. This
    // removes the mailer, logbook and organizations listeners.
    $event_dispatcher = $this->container->get('event_dispatcher');
    foreach ($event_dispatcher->getListeners() as $event => $listeners) {
      if (str_contains($event, 'projects')) {
        foreach ($listeners as $listener) {
          if (!str_contains($listener[0]::class, 'projects')) {
            $event_dispatcher->removeSubscriber($listener[0]);
          }
        }
      }
    }
  }

  /**
   * Creates a minimal project for testing purposes.
   */
  protected function createProject(ProjectState $state = ProjectState::Draft, string $role = 'organization'): ProjectInterface {

    $manager = Creative::create([
      'name' => $this->randomString(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);

    $organization = Organization::create([
      'name' => $this->randomString(),
      'mail' => $this->randomMachineName() . '@example.org',
      'field_manager' => $manager,
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole($role);
    $organization->save();
    $this->markEntityForCleanup($organization);

    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $this->randomString(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);

    $project_result = $project->getResult();
    $this->markEntityForCleanup($project_result);

    return $project;
  }

  /**
   * Creates a creative for testing purposes.
   */
  protected function createCreative(string $role = 'creative'): Creative {
    $creative = Creative::create([
      'name' => $this->randomString(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $creative->addRole($role);
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Creates a manager for testing purposes.
   */
  protected function createManager(): Creative {
    $manager = Creative::create([
      'name' => $this->randomString(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);
    return $manager;
  }

  /**
   * Creates a creative for testing purposes.
   */
  protected function createSupervisor(): Creative {
    $supervisor = Creative::create([
      'name' => $this->randomString(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $supervisor->addRole('supervisor');
    $supervisor->save();
    $this->markEntityForCleanup($supervisor);
    return $supervisor;
  }

  /**
   * Creates a organization for testing purposes.
   */
  protected function createOrganization(): Organization {
    $organization = Organization::create([
      'name' => $this->randomString(),
      'mail' => $this->randomMachineName() . '@example.org',
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

  /**
   * Reloads a project from storage, bypassing the caches.
   */
  protected function reloadProject(ProjectInterface $project): ProjectInterface {
    /** @var \Drupal\projects\ProjectInterface $reloaded */
    $reloaded = $this->container->get('entity_type.manager')->getStorage('project')->loadUnchanged($project->id());
    return $reloaded;
  }

  /**
   * Asserts the state and the last history item of a project.
   */
  protected function assertLastTransition(ProjectInterface $project, ProjectTransition $transition, ProjectState $from, ProjectState $to): void {
    $this->assertSame($to->value, $project->get('field_lifecycle')->value);
    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem $last */
    $last = $project->lifecycle()->history()->last();
    $this->assertSame($transition->value, $last->transition);
    $this->assertSame($from->value, $last->from);
    $this->assertSame($to->value, $last->to);
  }

}
