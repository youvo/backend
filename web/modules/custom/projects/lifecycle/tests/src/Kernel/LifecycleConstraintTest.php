<?php

declare(strict_types=1);

namespace Drupal\Tests\lifecycle\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests the field constraints.
 *
 * @group lifecycle
 */
class LifecycleConstraintTest extends WorkflowsTestBase {

  /**
   * @covers \Drupal\lifecycle\Plugin\Validation\Constraint\LifecycleConstraint
   * @covers \Drupal\lifecycle\Plugin\Validation\Constraint\LifecycleConstraintValidator
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testValidTransitionsNoViolations(): void {
    $user = $this->createUser([
      'use bureaucracy_workflow transition approved_project',
      'use bureaucracy_workflow transition ready_for_planning',
    ]);
    $this->setCurrentUser($user);

    $node = Node::create([
      'title' => 'Foo',
      'type' => 'project',
      'field_status' => 'in_discussion',
    ]);
    $node->save();

    // Same state does not cause a violation.
    $node->field_status->value = 'in_discussion';
    $violations = $node->validate();
    $this->assertCount(0, $violations);

    // A valid state does not cause a violation.
    $node->field_status->value = 'approved';
    $violations = $node->validate();
    $this->assertCount(0, $violations);
  }

  /**
   * Test we can not apply invalid transitions.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testInvalidTransition(): void {
    $node = Node::create([
      'title' => 'Foo',
      'type' => 'project',
      'field_status' => 'in_discussion',
    ]);
    $node->save();

    // Violation exists during invalid transition.
    $node->field_status->value = 'planning';
    $violations = $node->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('No transition exists to move from in_discussion to planning.', $violations[0]->getMessage());
  }

  /**
   * Test we cannot apply a valid transition unless we have permission.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testNotAllowedTransition(): void {
    $node = Node::create([
      'title' => 'Foo',
      'type' => 'project',
      'field_status' => 'in_discussion',
    ]);
    $node->save();

    $node->field_status->value = 'approved';
    $violations = $node->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals('You do not have sufficient permissions to use the Approved Project transition.', $violations[0]->getMessage());
  }

}
