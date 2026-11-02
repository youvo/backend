<?php

namespace Drupal\Tests\lifecycle\ExistingSite;

use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the lifecycle history field type on the project history of the site.
 */
#[Group('lifecycle')]
class LifecycleHistoryItemTest extends ProjectResourceTestBase {

  /**
   * Tests that items without a target state are not stored.
   */
  public function testEmptyItemsAreNotStored(): void {

    $project = $this->createProject();
    $project->set('field_lifecycle_history', [
      [
        'transition' => NULL,
        'from' => NULL,
        'to' => 'draft',
        'uid' => 1,
        'timestamp' => 1000,
      ],
      ['transition' => 'submit', 'from' => 'draft', 'uid' => 1, 'timestamp' => 2000],
      ['transition' => 'submit', 'from' => 'draft', 'to' => '', 'uid' => 1, 'timestamp' => 3000],
    ]);
    $project->save();

    $history = $this->reloadProject($project)->get('field_lifecycle_history');
    $this->assertCount(1, $history);
    $this->assertSame('draft', $history->getValue()[0]['to']);
  }

}
