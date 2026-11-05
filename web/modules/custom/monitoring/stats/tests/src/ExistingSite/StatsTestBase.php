<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Provides a base for the stats tests on the installed site.
 */
abstract class StatsTestBase extends ExistingSiteBase {

  /**
   * Creates a creative with roles of the site.
   */
  protected function createCreative(string ...$roles): Creative {
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    foreach ($roles ?: ['creative'] as $role) {
      $creative->addRole($role);
    }
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Creates an organization with a role of the site.
   */
  protected function createOrganizationUser(string $role): Organization {
    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'field_contact' => 'Olga Org',
      'mail' => $this->randomMachineName() . '@example.org',
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole($role);
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

}
