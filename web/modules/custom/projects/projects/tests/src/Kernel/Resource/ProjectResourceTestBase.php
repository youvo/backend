<?php

namespace Drupal\Tests\projects\Kernel\Resource;

use Drupal\creatives\Entity\Creative;
use Drupal\KernelTests\KernelTestBase;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\RequestTrait;

/**
 * Provides a test base for project resources.
 */
abstract class ProjectResourceTestBase extends KernelTestBase {

  use RequestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'basic_auth',
    'creatives',
    'field',
    'lifecycle',
    'options',
    'organizations',
    'projects',
    'projects_lifecycle_test',
    'projects_resource_test',
    'rest',
    'serialization',
    'system',
    'user',
    'user_bundle',
    'workflows',
    // @todo Split out smaller module for param converter.
    'youvo',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('project');
    $this->installEntitySchema('project_result');
    $this->installEntitySchema('user');
    $this->installConfig('projects_lifecycle_test');
    $this->installConfig('projects_resource_test');
  }

  /**
   * Creates a minimal project for testing purposes.
   */
  protected function createProject(ProjectState $state = ProjectState::DRAFT): ProjectInterface {

    $manager = Creative::create([
      'name' => $this->randomString(),
      'pass' => 'password',
      'status' => 1,
    ]);
    $manager->addRole('manager');
    $manager->save();

    $organization = Organization::create([
      'name' => $this->randomString(),
      'field_manager' => $manager,
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole('organization');
    $organization->save();

    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $this->randomString(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();

    return $project;
  }

}
