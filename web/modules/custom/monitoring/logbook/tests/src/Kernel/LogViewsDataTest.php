<?php

namespace Drupal\Tests\logbook\Kernel;

/**
 * Tests the views data of the log entity.
 *
 * @group logbook
 */
class LogViewsDataTest extends LogbookKernelTestBase {

  /**
   * The log entity provides the fields the logbook view needs.
   */
  public function testViewsDataExposesLogFields(): void {
    $data = $this->container->get('views.views_data')->get('log');
    foreach (['id', 'type', 'created', 'project', 'organization', 'author', 'message'] as $field) {
      $this->assertArrayHasKey($field, $data, "Views data has the field $field.");
    }
    $this->assertSame('date', $data['created']['filter']['id']);
    $this->assertSame('standard', $data['project']['relationship']['id']);
    $this->assertSame('standard', $data['organization']['relationship']['id']);
    $this->assertSame('standard', $data['author']['relationship']['id']);
  }

}
