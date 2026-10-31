<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\creatives\Entity\Creative;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
    'system',
    'views',
    'youvo',
    'tagify',
    'logbook',
  ];

  /**
   * The default author of created logs.
   */
  protected ?Creative $logAuthor = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('log');
    // The text of a log reads the names of creatives and organizations.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_name',
      'entity_type' => 'user',
      'type' => 'string',
    ]);
    $storage->save();
    foreach (['user', 'organization'] as $bundle) {
      FieldConfig::create(['field_storage' => $storage, 'bundle' => $bundle])->save();
    }
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
   * Creates and saves a log. The author defaults to a creative.
   */
  protected function createLog(string $type, array $values = []): Log {
    $this->logAuthor ??= $this->createCreative();
    $log = Log::create(['type' => $type] + $values + ['author' => $this->logAuthor->id()]);
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
