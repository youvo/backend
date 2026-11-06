<?php

namespace Drupal\Tests\consumer_permissions\Unit;

use Drupal\consumer_permissions\ConsumerPermissions;
use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oauth_grant\Consumers;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the consumer permission names.
 */
#[CoversClass(ConsumerPermissions::class)]
#[Group('consumer_permissions')]
class ConsumerPermissionsTest extends UnitTestCase {

  /**
   * Tests that permissions are named after the UUID of every known consumer.
   */
  public function testGenerate(): void {

    $known_uuid = Consumers::KNOWN['CREATIVE_LOCAL'][0];
    $known = $this->createMock(Consumer::class);
    $known->method('id')->willReturn('2');
    $known->method('uuid')->willReturn($known_uuid);
    $known->method('label')->willReturn('Renamed creatives');
    $known->method('getConfigDependencyKey')->willReturn('content');
    $known->method('getConfigDependencyName')->willReturn('consumer:consumer:' . $known_uuid);

    $other = $this->createMock(Consumer::class);
    $other->method('id')->willReturn('7');
    $other->method('uuid')->willReturn('0b3c9a52-2f0e-4c64-9e0e-6a3a0f8f1d11');
    $other->method('label')->willReturn('Other');
    $other->method('getConfigDependencyKey')->willReturn('content');
    $other->method('getConfigDependencyName')->willReturn('consumer:consumer:0b3c9a52-2f0e-4c64-9e0e-6a3a0f8f1d11');

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([2 => $known, 7 => $other]);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->with('consumer')->willReturn($storage);

    $consumer_permissions = new ConsumerPermissions($entity_type_manager);
    $consumer_permissions->setStringTranslation($this->getStringTranslationStub());
    $permissions = $consumer_permissions->generate();

    // Every known consumer has a permission, whether it exists or not.
    $expected = array_map(fn (array $consumer) => ConsumerPermissions::PREFIX . $consumer[0], Consumers::KNOWN);
    $expected[] = ConsumerPermissions::PREFIX . '0b3c9a52-2f0e-4c64-9e0e-6a3a0f8f1d11';
    $this->assertEqualsCanonicalizing(array_values($expected), array_keys($permissions));
    $this->assertArrayNotHasKey(ConsumerPermissions::PREFIX . '2', $permissions);

    // Existing consumers name the permission after their label.
    $permission = $permissions[ConsumerPermissions::PREFIX . $known_uuid];
    $this->assertSame('Authorize with client <em class="placeholder">Renamed creatives</em>', (string) $permission['title']);
    $this->assertSame(['content' => ['consumer:consumer:' . $known_uuid]], $permission['dependencies']);

    // Missing known consumers use the label from the list.
    $missing_uuid = Consumers::KNOWN['ORGANIZATION'][0];
    $permission = $permissions[ConsumerPermissions::PREFIX . $missing_uuid];
    $this->assertSame('Authorize with client <em class="placeholder">Organizations</em>', (string) $permission['title']);
    $this->assertSame(['content' => ['consumer:consumer:' . $missing_uuid]], $permission['dependencies']);
  }

}
