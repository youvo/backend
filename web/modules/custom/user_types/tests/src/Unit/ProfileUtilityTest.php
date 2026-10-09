<?php

namespace Drupal\Tests\user_types\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user_types\Utility\Profile;

/**
 * Test coverage for the profile utility class.
 *
 * @coversDefaultClass \Drupal\user_types\Utility\Profile
 * @group user_types
 */
class ProfileUtilityTest extends UnitTestCase {

  /**
   * The mock account.
   */
  protected AccountInterface $account;

  /**
   * The mock account proxy.
   */
  protected AccountProxyInterface $accountProxy;

  /**
   * The mock creative.
   */
  protected AccountInterface $creative;

  /**
   * The mock organization.
   */
  protected AccountInterface $organization;

  /**
   * The mock creative auth user.
   */
  protected TokenAuthUserInterface $creativeAuthUser;

  /**
   * The mock organization auth user.
   */
  protected TokenAuthUserInterface $organizationAuthUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();

    $this->account = $this->createMock(AccountInterface::class);
    $this->account->method('id')->willReturn(1);

    $this->creative = $this->createMock(Creative::class);
    $this->creative->method('id')->willReturn(3);

    $this->accountProxy = $this->createMock(AccountProxyInterface::class);
    $this->accountProxy->method('id')->willReturn(2);
    // For testing, we will proxy the creative.
    $this->accountProxy->method('getAccount')->willReturn($this->creative);

    $this->organization = $this->createMock(Organization::class);
    $this->organization->method('id')->willReturn(4);

    // The token auth user decorates the user entity. We mock the interface
    // because the implementation is internal to the simple_oauth module.
    $this->creativeAuthUser = $this->createMock(TokenAuthUserInterface::class);
    $this->creativeAuthUser->method('id')->willReturn(3);
    $this->creativeAuthUser->method('getSubject')->willReturn($this->creative);

    $this->organizationAuthUser = $this->createMock(TokenAuthUserInterface::class);
    $this->organizationAuthUser->method('id')->willReturn(4);
    $this->organizationAuthUser->method('getSubject')->willReturn($this->organization);
  }

  /**
   * Tests the id method.
   *
   * @covers ::id
   */
  public function testId(): void {
    $this->assertSame(1, Profile::id($this->account));
    $this->assertSame(2, Profile::id($this->accountProxy));
    $this->assertSame(3, Profile::id($this->creative));
    $this->assertSame(3, Profile::id($this->creativeAuthUser));
    $this->assertSame(4, Profile::id($this->organization));
    $this->assertSame(4, Profile::id($this->organizationAuthUser));
    $this->assertSame(123, Profile::id(123));
    $this->assertNotSame(123, Profile::id(321));
  }

  /**
   * Tests the account method.
   *
   * @covers ::account
   */
  public function testAccount(): void {
    $this->assertSame($this->account, Profile::account($this->account));
    $this->assertSame($this->creative, Profile::account($this->creative));
    $this->assertSame($this->creative, Profile::account($this->accountProxy));
    $this->assertSame($this->creative, Profile::account($this->creativeAuthUser));
    $this->assertSame($this->organization, Profile::account($this->organization));
    $this->assertSame($this->organization, Profile::account($this->organizationAuthUser));
  }

  /**
   * Tests the isCreative method.
   *
   * @covers ::isCreative
   * @covers ::account
   */
  public function testIsCreative(): void {
    $this->assertTrue(Profile::isCreative($this->accountProxy));
    $this->assertTrue(Profile::isCreative($this->creative));
    $this->assertTrue(Profile::isCreative($this->creativeAuthUser));
    $this->assertFalse(Profile::isCreative($this->organization));
    $this->assertFalse(Profile::isCreative($this->organizationAuthUser));
  }

  /**
   * Tests the isOrganization method.
   *
   * @covers ::isOrganization
   * @covers ::account
   */
  public function testIsOrganization(): void {
    $this->assertFalse(Profile::isOrganization($this->accountProxy));
    $this->assertFalse(Profile::isOrganization($this->creative));
    $this->assertFalse(Profile::isOrganization($this->creativeAuthUser));
    $this->assertTrue(Profile::isOrganization($this->organization));
    $this->assertTrue(Profile::isOrganization($this->organizationAuthUser));
  }

}
