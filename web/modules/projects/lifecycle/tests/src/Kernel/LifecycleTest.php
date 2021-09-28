<?php

namespace Drupal\Tests\lifecycle\Kernel;

use Drupal\node\Entity\Node;
use Drupal\workflows\Entity\Workflow;
use Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem;

/**
 * Test the lifecycle.
 *
 * @group lifecycle
 */
class LifecycleTest extends WorkflowsTestBase {

  /**
   * Test the implementation of OptionsProviderInterface.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testOptionsProvider() {
    $node = Node::create([
      'title' => 'Foo',
      'type' => 'project',
      'field_status' => 'in_discussion',
    ]);
    $node->save();

    $this->assertEquals([
      'implementing' => 'Implementing',
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      'planning' => 'Planning',
      'in_discussion' => 'In Discussion',
    ], $node->field_status[0]->getPossibleOptions());
    $this->assertEquals([
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      'in_discussion' => 'In Discussion',
    ], $node->field_status[0]->getSettableOptions());

    $this->assertEquals([
      'implementing',
      'approved',
      'rejected',
      'planning',
      'in_discussion',
    ], $node->field_status[0]->getPossibleValues());
    $this->assertEquals([
      'approved',
      'rejected',
      'in_discussion',
    ], $node->field_status[0]->getSettableValues());
  }

  /**
   * Settable options are filtered by the users permissions.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testOptionsProviderFilteredByUser() {
    $node = Node::create([
      'title' => 'Foo',
      'type' => 'project',
      'field_status' => 'in_discussion',
    ]);
    $node->save();

    // If a user has no permissions then the only available state is the current
    // state.
    $this->assertEquals([
      'in_discussion' => 'In Discussion',
    ], $node->field_status[0]->getSettableOptions($this->createUser()));

    // Grant the ability to use the approved_project transition and the user
    // should now be able to set the Approved state.
    $this->assertEquals([
      'in_discussion' => 'In Discussion',
      'approved' => 'Approved',
    ], $node->field_status[0]->getSettableOptions($this->createUser(['use bureaucracy_workflow transition approved_project'])));
  }

  /**
   * @covers \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFieldType() {
    $node = Node::create([
      'title' => 'Foo',
      'type' => 'project',
      'field_status' => 'in_discussion',
    ]);
    $node->save();

    // Test the dependencies' calculation.
    $this->assertEquals([
      'config' => [
        'workflows.workflow.bureaucracy_workflow',
      ],
    ], LifecycleItem::calculateStorageDependencies($node->field_status->getFieldDefinition()->getFieldStorageDefinition()));

    // Test the getWorkflow method.
    $this->assertEquals('bureaucracy_workflow', $node->field_status[0]->getWorkflow()->id());
  }

  /**
   * @covers \Drupal\lifecycle\Plugin\WorkflowType\Lifecycle
   */
  public function testWorkflowType() {
    // Test the initial state based on the config, despite the state weights.
    $type = Workflow::load('bureaucracy_workflow')->getTypePlugin();
    $this->assertEquals('in_discussion', $type->getInitialState()->id());
  }

}
