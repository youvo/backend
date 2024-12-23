<?php

namespace Drupal\Tests\user_types\Unit;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\simple_oauth\Authentication\TokenAuthUser;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Drupal\simple_oauth\Entity\Oauth2TokenInterface;
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
  protected Creative $creative;

  /**
   * The mock organization.
   */
  protected Organization $organization;

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
    $this->account->expects($this->any())
      ->method('id')
      ->willReturn(1);

    $this->creative = $this->createMock(Creative::class);
    $this->creative->expects($this->any())
      ->method('id')
      ->willReturn(3);
    // The creative user has the bundle "user" to ensure better compatibility
    // with the user_bundle contrib module.
    $this->creative->expects($this->any())
      ->method('bundle')
      ->willReturn('user');

    $this->accountProxy = $this->createMock(AccountProxyInterface::class);
    $this->accountProxy->expects($this->any())
      ->method('id')
      ->willReturn(2);
    // For testing, we will proxy the creative.
    $this->accountProxy->expects($this->any())
      ->method('getAccount')
      ->willReturn($this->creative);

    $this->organization = $this->createMock(Organization::class);
    $this->organization->expects($this->any())
      ->method('id')
      ->willReturn(4);
    $this->organization->expects($this->any())
      ->method('bundle')
      ->willReturn('organization');

    $consumer = $this->createMock(ConsumerInterface::class);
    $consumer_field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $consumer_field->expects($this->any())
      ->method('__get')
      ->with('entity')
      ->willReturn($consumer);

    $creative_field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $creative_field->expects($this->any())
      ->method('__get')
      ->with('entity')
      ->willReturn($this->creative);
    $creative_token = $this->createMock(Oauth2TokenInterface::class);
    $creative_token->expects($this->any())
      ->method('get')
      ->willReturnOnConsecutiveCalls($consumer_field, $creative_field);
    $this->creativeAuthUser = new TokenAuthUser($creative_token);

    $organization_field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $organization_field->expects($this->any())
      ->method('__get')
      ->with('entity')
      ->willReturn($this->organization);
    $organization_token = $this->createMock(Oauth2TokenInterface::class);
    $organization_token->expects($this->any())
      ->method('get')
      ->willReturnOnConsecutiveCalls($consumer_field, $organization_field);
    $this->organizationAuthUser = new TokenAuthUser($organization_token);
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
   * Tests the isCreative method.
   *
   * @covers ::isCreative
   * @covers ::isUserType
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
   * @covers ::isUserType
   */
  public function testIsOrganization(): void {
    $this->assertFalse(Profile::isOrganization($this->accountProxy));
    $this->assertFalse(Profile::isOrganization($this->creative));
    $this->assertFalse(Profile::isOrganization($this->creativeAuthUser));
    $this->assertTrue(Profile::isOrganization($this->organization));
    $this->assertTrue(Profile::isOrganization($this->organizationAuthUser));
  }

}
