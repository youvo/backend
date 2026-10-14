<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\logbook\Entity\Log;
use Drupal\logbook\Entity\LogPattern;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;

/**
 * Provides a test base for logbook kernel tests.
 */
abstract class LogbookKernelTestBase extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'language',
    'views',
    'youvo',
    'logbook',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('log');
  }

  /**
   * Creates a log pattern. All flags default to FALSE except status.
   */
  protected function createPattern(string $id, array $values = []): LogPattern {
    $pattern = LogPattern::create($values + [
      'id' => $id,
      'label' => 'Project ' . ucfirst(str_replace('project_', '', $id)),
      'tokens' => [],
      'status' => TRUE,
      'detectable' => FALSE,
      'observable' => FALSE,
      'public' => FALSE,
      'promote' => FALSE,
      'hidden' => FALSE,
      'color' => '',
      'tone' => '',
    ]);
    $pattern->save();
    return $pattern;
  }

  /**
   * Creates and saves a log.
   */
  protected function createLog(string $type, array $values = []): Log {
    $log = Log::create(['type' => $type] + $values);
    $log->save();
    return $log;
  }

  /**
   * Creates and saves a project.
   */
  protected function createLogbookProject(string $title = 'Project'): ProjectInterface {
    $project = $this->createProject(ProjectState::Open);
    $project->setTitle($title);
    $project->save();
    return $project;
  }

}
