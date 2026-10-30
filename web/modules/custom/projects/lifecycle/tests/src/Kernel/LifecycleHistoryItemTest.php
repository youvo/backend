<?php

declare(strict_types=1);

namespace Drupal\Tests\lifecycle\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem;

/**
 * Tests the lifecycle history field type.
 *
 * @coversDefaultClass \Drupal\lifecycle\Plugin\Field\FieldType\LifecycleHistoryItem
 * @group lifecycle
 */
class LifecycleHistoryItemTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['options', 'workflows', 'lifecycle'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    FieldStorageConfig::create([
      'field_name' => 'field_history',
      'entity_type' => 'entity_test',
      'type' => 'lifecycle_history_item',
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_history',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * @covers ::mainPropertyName
   */
  public function testMainProperty(): void {
    $this->assertSame('to', LifecycleHistoryItem::mainPropertyName());
  }

  /**
   * @covers ::isEmpty
   */
  public function testEmptyItemsAreNotStored(): void {
    $entity = EntityTest::create([
      'field_history' => [
        [
          'transition' => NULL,
          'from' => NULL,
          'to' => 'draft',
          'uid' => 1,
          'timestamp' => 1000,
        ],
        ['transition' => 'submit', 'from' => 'draft', 'uid' => 1, 'timestamp' => 2000],
      ],
    ]);
    $entity->save();

    $history = EntityTest::load($entity->id())->get('field_history');
    $this->assertCount(1, $history);
    $this->assertSame('draft', $history->getValue()[0]['to']);
  }

}
