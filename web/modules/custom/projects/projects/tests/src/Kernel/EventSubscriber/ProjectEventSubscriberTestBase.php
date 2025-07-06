<?php

namespace Drupal\Tests\projects\Kernel\EventSubscriber;

use Drupal\creatives\Entity\Creative;
use Drupal\KernelTests\KernelTestBase;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a test base for project event subscribers.
 */
abstract class ProjectEventSubscriberTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'creatives',
    'field',
    'lifecycle',
    'options',
    'organizations',
    'projects',
    'projects_lifecycle_test',
    'user',
    'user_bundle',
    'workflows',
  ];

  /**
   * The event dispatcher.
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('project');
    $this->installEntitySchema('project_result');
    $this->installEntitySchema('user');
    $this->installConfig('projects_lifecycle_test');
    $this->eventDispatcher = $this->container->get('event_dispatcher');
  }

  /**
   * Creates a minimal project for testing purposes.
   */
  protected function createProject(ProjectState $state = ProjectState::Draft): ProjectInterface {

    $manager = Creative::create(['name' => $this->randomString()]);
    $manager->save();

    $organization = Organization::create([
      'name' => $this->randomString(),
      'field_manager' => $manager,
    ]);
    $organization->save();

    return Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $this->randomString(),
      'field_lifecycle' => $state->value,
    ]);
  }

  /**
   * Creates a creative for testing purposes.
   */
  protected function createCreative(): Creative {
    $creative = Creative::create(['name' => $this->randomString()]);
    $creative->save();
    return $creative;
  }

}
