<?php

namespace Drupal\Tests\organizations\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the backend profile of an organization with the theme of the site.
 */
#[Group('organizations')]
class OrganizationProfileTest extends ExistingSiteBase {

  /**
   * A private field of the display shows only to the organization's manager.
   *
   * The profile is rendered with the render cache, first for the manager of
   * the organization, then for another manager with the same permissions.
   * The second must not get the cached profile of the first.
   */
  public function testPrivateFieldPerManager(): void {

    $manager = $this->createManager();
    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.org',
      'status' => 1,
      'field_name' => 'Nachbarschaftshilfe Ost',
      'field_manager' => $manager,
      'field_reachability' => 'Dienstags erreichbar',
    ]);
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);

    $this->assertStringContainsString('Dienstags erreichbar', $this->renderProfile($organization, $manager));
    $this->assertStringNotContainsString('Dienstags erreichbar', $this->renderProfile($organization, $this->createManager()));
  }

  /**
   * Creates a manager.
   */
  protected function createManager(): Creative {
    $manager = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);
    return $manager;
  }

  /**
   * Renders the full profile of an organization for an account.
   *
   * The build keeps its cache keys, so the render cache is used.
   */
  protected function renderProfile(Organization $organization, UserInterface $account): string {
    $this->container->get('current_user')->setAccount($account);
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('user')
      ->view($organization, 'full');
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
