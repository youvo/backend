<?php

declare(strict_types=1);

namespace Drupal\Tests\lifecycle\Kernel;

use Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the lifecycle workflow type, field type, formatter and constraint.
 *
 * The fixture workflow orders its states against their weights. It stays a
 * Kernel test, because the site must not get the fixture configuration.
 */
#[Group('lifecycle')]
#[RunTestsInSeparateProcesses]
class LifecycleTest extends WorkflowsTestBase {

  /**
   * Creates a saved node in the initial state of the fixture workflow.
   */
  protected function createNode(): NodeInterface {
    $node = Node::create([
      'title' => 'Foo',
      'type' => 'project',
      'field_status' => 'in_discussion',
    ]);
    $node->save();
    return $node;
  }

  /**
   * Tests the workflow type, the field type and the states list formatter.
   */
  public function testLifecycleField(): void {

    // The initial state comes from the config, despite the state weights.
    $type = Workflow::load('bureaucracy_workflow')->getTypePlugin();
    $this->assertEquals('in_discussion', $type->getInitialState()->id());

    $node = $this->createNode();
    $this->assertEquals([
      'config' => [
        'workflows.workflow.bureaucracy_workflow',
      ],
    ], LifecycleItem::calculateStorageDependencies($node->get('field_status')->getFieldDefinition()->getFieldStorageDefinition()));

    /** @var \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleItem $field_status */
    $field_status = $node->get('field_status')->first();
    $this->assertEquals('bureaucracy_workflow', $field_status->getWorkflow()->id());

    // The options follow the state weights.
    $this->assertEquals([
      'implementing' => 'Implementing',
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      'planning' => 'Planning',
      'in_discussion' => 'In Discussion',
    ], $field_status->getPossibleOptions());
    $this->assertEquals([
      'implementing',
      'approved',
      'rejected',
      'planning',
      'in_discussion',
    ], $field_status->getPossibleValues());
    $this->assertEquals([
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      'in_discussion' => 'In Discussion',
    ], $field_status->getSettableOptions());
    $this->assertEquals([
      'approved',
      'rejected',
      'in_discussion',
    ], $field_status->getSettableValues());

    // Settable options are filtered by the permissions of the user. Without
    // permissions only the current state is available.
    $this->assertEquals([
      'in_discussion' => 'In Discussion',
    ], $field_status->getSettableOptions($this->createUser()));
    $this->assertEquals([
      'in_discussion' => 'In Discussion',
      'approved' => 'Approved',
    ], $field_status->getSettableOptions($this->createUser(['use bureaucracy_workflow transition approved_project'])));

    // The states list formatter marks the current state and the states
    // before it, and leaves out the excluded states.
    $output = $node->get('field_status')->view(['type' => 'lifecycle_state_list']);
    $classes = array_map(static fn(array $item) => [$item['#value'] => $item['#wrapper_attributes']['class']], $output[0]['#items']);
    $this->assertEquals([
      ['Implementing' => ['implementing', 'before-current']],
      ['Approved' => ['approved', 'before-current']],
      ['Rejected' => ['rejected', 'before-current']],
      ['Planning' => ['planning', 'before-current']],
      ['In Discussion' => ['in_discussion', 'is-current']],
    ], $classes);
    $output = $node->get('field_status')->view([
      'type' => 'lifecycle_state_list',
      'settings' => [
        'excluded_states' => [
          'in_discussion' => 'in_discussion',
          'planning' => 'planning',
          'rejected' => 'rejected',
          'approved' => 'approved',
        ],
      ],
    ]);
    $this->assertCount(1, $output[0]['#items']);
    $this->assertSame('Implementing', $output[0]['#items'][0]['#value']);
  }

  /**
   * Tests that the constraint validates the transitions and their permissions.
   */
  public function testConstraint(): void {

    // An invalid transition causes a violation.
    $node = $this->createNode();
    $node->get('field_status')->value = 'planning';
    $violations = $node->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('No transition exists to move from in_discussion to planning.', $violations[0]->getMessage());

    // A valid transition requires the permission.
    $node = $this->createNode();
    $node->get('field_status')->value = 'approved';
    $violations = $node->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('You do not have sufficient permissions to use the Approved Project transition.', $violations[0]->getMessage());

    $this->setCurrentUser($this->createUser([
      'use bureaucracy_workflow transition approved_project',
      'use bureaucracy_workflow transition ready_for_planning',
    ]));

    // The same state does not cause a violation.
    $node = $this->createNode();
    $node->get('field_status')->value = 'in_discussion';
    $this->assertCount(0, $node->validate());

    // A valid transition with the permission does not cause a violation.
    $node->get('field_status')->value = 'approved';
    $this->assertCount(0, $node->validate());
  }

}
