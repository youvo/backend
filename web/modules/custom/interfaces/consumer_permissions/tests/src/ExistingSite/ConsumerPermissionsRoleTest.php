<?php

namespace Drupal\Tests\consumer_permissions\ExistingSite;

use Drupal\consumer_permissions\ConsumerPermissions;
use Drupal\oauth_grant\Consumers;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests that roles keep the permissions of the known consumers.
 *
 * Each environment creates only some of the known consumers. A role saved
 * outside a config import drops undefined permissions, so the permissions of
 * all known consumers must exist on every environment.
 */
#[Group('consumer_permissions')]
class ConsumerPermissionsRoleTest extends ExistingSiteBase {

  /**
   * Tests that a saved role keeps the permissions of all known consumers.
   */
  public function testRoleKeepsKnownConsumers(): void {

    $permissions = [];
    $dependencies = [];
    foreach (Consumers::KNOWN as [$uuid]) {
      $permissions[] = ConsumerPermissions::PREFIX . $uuid;
      $dependencies[] = 'consumer:consumer:' . $uuid;
    }

    $role = Role::load($this->createRole($permissions));
    $role->save();
    $role = Role::load($role->id());

    $this->assertEqualsCanonicalizing($permissions, $role->getPermissions());
    $this->assertEqualsCanonicalizing($dependencies, $role->getDependencies()['content']);
  }

}
