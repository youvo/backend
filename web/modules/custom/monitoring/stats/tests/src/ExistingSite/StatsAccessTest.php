<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\organizations\Entity\Organization;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the access to the stats page with the site roles.
 */
#[Group('stats')]
class StatsAccessTest extends StatsTestBase {

  /**
   * Staff roles see the stats page, creatives, organizations and guests not.
   */
  public function testAccessByRole(): void {

    $this->drupalGet('stats');
    $this->assertSession()->statusCodeEquals(403);

    foreach (['manager', 'supervisor', 'observer'] as $role) {
      $this->drupalLogin($this->createCreative('creative', $role));
      $this->drupalGet('stats');
      $this->assertSession()->statusCodeEquals(200);
    }

    $this->drupalLogin($this->createCreative('creative'));
    $this->drupalGet('stats');
    $this->assertSession()->statusCodeEquals(403);

    foreach ([Organization::ROLE_ORGANIZATION, 'prospect'] as $role) {
      $this->drupalLogin($this->createOrganizationUser($role));
      $this->drupalGet('stats');
      $this->assertSession()->statusCodeEquals(403);
    }
  }

}
