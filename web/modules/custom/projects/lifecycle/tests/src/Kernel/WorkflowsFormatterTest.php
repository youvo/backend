<?php

declare(strict_types=1);

namespace Drupal\Tests\lifecycle\Kernel;

use Drupal\node\Entity\Node;

/**
 * Tests the Lifecycle formatters.
 *
 * @group lifecycle
 */
class WorkflowsFormatterTest extends WorkflowsTestBase {

  /**
   * Tests the states list formatter.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testStatesListFormatter(): void {
    $node = Node::create([
      'title' => 'Foo',
      'type' => 'project',
      'field_status' => 'in_discussion',
    ]);
    $node->save();

    $output = $node->field_status->view(['type' => 'lifecycle_state_list']);
    $this->assertEquals([
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => 'Implementing',
        '#wrapper_attributes' => ['class' => ['implementing', 'before-current']],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => 'Approved',
        '#wrapper_attributes' => ['class' => ['approved', 'before-current']],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => 'Rejected',
        '#wrapper_attributes' => ['class' => ['rejected', 'before-current']],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => 'Planning',
        '#wrapper_attributes' => ['class' => ['planning', 'before-current']],
      ],
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => 'In Discussion',
        '#wrapper_attributes' => ['class' => ['in_discussion', 'is-current']],
      ],
    ], $output[0]['#items']);

    // Try with settings excluded.
    $output = $node->field_status->view([
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
    $this->assertEquals([
      [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => 'Implementing',
        '#wrapper_attributes' => ['class' => ['implementing', 'before-current']],
      ],
    ], $output[0]['#items']);
  }

}
