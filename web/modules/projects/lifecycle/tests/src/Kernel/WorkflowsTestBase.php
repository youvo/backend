<?php

namespace Drupal\Tests\lifecycle\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class for testing.
 *
 * @group lifecycle
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
    'lifecycle',
    'field',
    'lifecycle_test_workflows',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('workflow');
    $this->installConfig(['lifecycle_test_workflows']);
    $this->installSchema('system', ['sequences']);

    // Discard user 1.
    $this->createUser();
  }

}
