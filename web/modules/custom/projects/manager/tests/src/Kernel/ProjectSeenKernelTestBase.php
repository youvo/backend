<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\Entity\LogPattern;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Provides a test base for the seen tracking of the manager.
 */
abstract class ProjectSeenKernelTestBase extends MailerKernelTestBase {

  use UserCreationTrait;

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
   * The creative that writes logs unless a test says otherwise.
   */
  protected ?Creative $logAuthor = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('log');
    $this->installEntitySchema('log_text');
    $this->installSchema('manager', ['manager_project_seen']);
    // User 1 bypasses permission checks: use it up.
    $this->createMailableCreative('Root');
    $this->createPattern('project_apply', TRUE);
    $this->createPattern('project_publish', TRUE);
    $this->createPattern('project_notify', FALSE);
    $this->createPattern('project_reset', TRUE, FALSE);
  }

  /**
   * Creates a log pattern.
   */
  protected function createPattern(string $id, bool $observable, bool $status = TRUE): void {
    LogPattern::create([
      'id' => $id,
      'label' => 'Project ' . ucfirst(str_replace('project_', '', $id)),
      'tokens' => [],
      'status' => $status,
      'detectable' => FALSE,
      'observable' => $observable,
      'public' => FALSE,
      'promote' => FALSE,
      'hidden' => FALSE,
      'color' => '',
      'tone' => '',
    ])->save();
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
   * Creates a saved project of an organization.
   */
  protected function createProjectFor(Organization $organization, ProjectState $state, string $title = 'Project'): ProjectInterface {
    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $title,
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    return $project;
  }

  /**
   * Creates a log of a project. The author defaults to another creative.
   */
  protected function createProjectLog(ProjectInterface $project, string $type = 'project_apply', ?UserInterface $author = NULL): Log {
    $this->logAuthor ??= $this->createMailableCreative('Writer');
    $log = Log::create([
      'type' => $type,
      'project' => $project->id(),
      'author' => ($author ?? $this->logAuthor)->id(),
    ]);
    $log->save();
    return $log;
  }

}
