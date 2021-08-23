<?php

namespace Drupal\Tests\youvo_lifecycle\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class for testing.
 *
 * @group youvo_lifecycle
 */
abstract class WorkflowsTestBase extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'system',
    'user',
    'node',
    'options',
    'workflows',
    'youvo_lifecycle',
    'field',
    'youvo_lifecycle_test_workflows',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('workflow');
    $this->installConfig(['youvo_lifecycle_test_workflows']);
    $this->installSchema('system', ['sequences']);

    // Discard user 1.
    $this->createUser();
  }

}
