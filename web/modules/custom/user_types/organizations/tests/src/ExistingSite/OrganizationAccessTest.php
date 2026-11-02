<?php

namespace Drupal\Tests\organizations\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Access\OrganizationEntityAccess;
use Drupal\organizations\Access\OrganizationFieldAccess;
use Drupal\organizations\Entity\Organization;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the organization field and manage access with the site roles.
 */
#[CoversClass(OrganizationFieldAccess::class)]
#[CoversClass(OrganizationEntityAccess::class)]
#[Group('organizations')]
class OrganizationAccessTest extends ExistingSiteBase {

  /**
   * The manager of the organization.
   */
  protected Creative $manager;

  /**
   * The organization under test.
   */
  protected Organization $organization;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manager = $this->createCreative(['creative', 'manager']);
    $this->organization = $this->createOrganization($this->manager);
  }

  /**
   * Tests the field access per role.
   */
  public function testFieldAccess(): void {
    $other_manager = $this->createCreative(['creative', 'manager']);
    $supervisor = $this->createCreative(['supervisor']);

    // The organization and its manager view private fields and edit their
    // fields, but not the fields set by the backend.
    foreach ([$this->organization, $this->manager] as $account) {
      $this->assertTrue($this->fieldAccess('field_phone', 'view', $account));
      $this->assertTrue($this->fieldAccess('field_phone', 'edit', $account));
      $this->assertFalse($this->fieldAccess('field_referral', 'edit', $account));
    }

    // Other managers only view public fields.
    $this->assertTrue($this->fieldAccess('field_name', 'view', $other_manager));
    $this->assertFalse($this->fieldAccess('field_phone', 'view', $other_manager));

    // Supervisors bypass the field checks.
    $this->assertTrue($this->fieldAccess('field_phone', 'view', $supervisor));
    $this->assertTrue($this->fieldAccess('field_referral', 'edit', $supervisor));
  }

  /**
   * Tests the manage access per role.
   */
  public function testManageAccess(): void {
    $access = new OrganizationEntityAccess();
    $supervisor = $this->createCreative(['supervisor']);
    $creative = $this->createCreative(['creative']);

    $this->assertTrue($access->accessManage($this->manager, $this->organization)->isAllowed());
    $this->assertTrue($access->accessManage($supervisor, $this->organization)->isAllowed());
    $this->assertFalse($access->accessManage($this->organization, $this->organization)->isAllowed());
    $this->assertFalse($access->accessManage($creative, $this->organization)->isAllowed());
    $this->assertFalse($access->accessManage($supervisor, $creative)->isAllowed());
  }

  /**
   * Checks the field access of an account to the organization.
   */
  protected function fieldAccess(string $field_name, string $operation, UserInterface $account): bool {
    return $this->organization->get($field_name)->access($operation, $account);
  }

  /**
   * Creates a creative with roles.
   *
   * @param string[] $roles
   *   The roles of the creative.
   */
  protected function createCreative(array $roles): Creative {
    $creative = Creative::create($this->accountValues());
    foreach ($roles as $role) {
      $creative->addRole($role);
    }
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Creates an organization with a manager.
   */
  protected function createOrganization(Creative $manager): Organization {
    $organization = Organization::create(['field_manager' => $manager] + $this->accountValues());
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

  /**
   * Provides the values of an account with a unique name and mail address.
   */
  protected function accountValues(): array {
    return [
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ];
  }

}
