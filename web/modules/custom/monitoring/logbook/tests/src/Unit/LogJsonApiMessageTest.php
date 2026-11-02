<?php

namespace Drupal\Tests\logbook\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that JSON:API never exposes the message of a log.
 *
 * Messages hold private texts, such as applications or manager mails. A log
 * pattern without resource config would expose all fields, so each pattern
 * needs one.
 *
 * @group logbook
 */
class LogJsonApiMessageTest extends UnitTestCase {

  /**
   * Each log pattern has a resource config that disables the message.
   */
  public function testMessageDisabled(): void {
    $directories = [
      $this->root . '/../config/sync',
      dirname(__DIR__, 3) . '/config/install',
    ];
    foreach ($directories as $directory) {
      $patterns = glob($directory . '/logbook.log_pattern.*.yml') ?: [];
      $this->assertNotEmpty($patterns, "$directory has log patterns.");
      foreach ($patterns as $pattern) {
        $bundle = basename($pattern, '.yml');
        $bundle = substr($bundle, strlen('logbook.log_pattern.'));
        $file = $directory . '/jsonapi_extras.jsonapi_resource_config.log--' . $bundle . '.yml';
        $this->assertFileExists($file);
        $values = Yaml::decode((string) file_get_contents($file));
        $this->assertTrue($values['resourceFields']['message']['disabled'] ?? FALSE, "$file disables the message.");
      }
    }
  }

}
