<?php

namespace Drupal\Tests\organizations\ExistingSite;

use Drupal\organizations\Entity\Organization;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the address of an organization.
 */
#[Group('organizations')]
class OrganizationAddressTest extends ExistingSiteBase {

  /**
   * Tests the address with and without a country.
   */
  public function testAddress(): void {
    $organization = Organization::create([
      'field_street' => 'Hafenweg 1',
      'field_zip' => '20095',
      'field_city' => 'Hamburg',
    ]);
    $this->assertSame('Hafenweg 1, 20095 Hamburg', $organization->getAddress());
    $organization->set('field_country', 'Deutschland');
    $this->assertSame('Hafenweg 1, 20095 Hamburg, Deutschland', $organization->getAddress());
  }

}
