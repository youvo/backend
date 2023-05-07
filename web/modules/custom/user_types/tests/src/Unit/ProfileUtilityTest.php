<?php

namespace Drupal\Tests\user_types\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\simple_oauth\Authentication\TokenAuthUser;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user_types\Utility\Profile;

/**
 * Test coverage for the profile utility class.
 *
 * @group user_types
 */
class ProfileUtilityTest extends UnitTestCase {

  /**
   * The account mock user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The account proxy mock user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $accountProxy;

  /**
   * The mock creative.
   *
   * @var \Drupal\creatives\Entity\Creative
   */
  protected Creative $creative;

  /**
   * The mock creative.
   *
   * @var \Drupal\organizations\Entity\Organization
   */
  protected Organization $organization;

  /**
   * The mock creative auth user.
   *
   * @var \Drupal\simple_oauth\Authentication\TokenAuthUserInterface
   */
  protected TokenAuthUserInterface $creativeAuthUser;

  /**
   * The mock organization auth user.
   *
   * @var \Drupal\simple_oauth\Authentication\TokenAuthUserInterface
   */
  protected TokenAuthUserInterface $organizationAuthUser;

  /**
   * {@inheritdoc}
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   When the user can not be identified.
   */
  protected function setUp(): void {

    parent::setUp();

    $this->account = $this->createMock('\Drupal\Core\Session\AccountInterface');
    $this->account->expects($this->any())
      ->method('id')
      ->willReturn(1);

    $this->accountProxy = $this->createMock('\Drupal\Core\Session\AccountProxyInterface');
    $this->accountProxy->expects($this->any())
      ->method('id')
      ->willReturn(2);

    $this->creative = $this->createMock(Creative::class);
    $this->creative->expects($this->any())
      ->method('id')
      ->willReturn(3);
    // The creative user has the bundle "user" to ensure better compatibility
    // with the user_bundle contrib module.
    $this->creative->expects($this->any())
      ->method('bundle')
      ->willReturn('user');

    $this->organization = $this->createMock(Organization::class);
    $this->organization->expects($this->any())
      ->method('id')
      ->willReturn(4);
    $this->organization->expects($this->any())
      ->method('bundle')
      ->willReturn('organization');

    $consumer = $this->createMock('Drupal\consumers\Entity\ConsumerInterface');
    $consumer_field = $this->createMock('Drupal\Core\Field\EntityReferenceFieldItemListInterface');
    $consumer_field->expects($this->any())
      ->method('__get')
      ->with('entity')
      ->willReturn($consumer);

    $creative_field = $this->createMock('Drupal\Core\Field\EntityReferenceFieldItemListInterface');
    $creative_field->expects($this->any())
      ->method('__get')
      ->with('entity')
      ->willReturn($this->creative);
    $creative_token = $this->createMock('Drupal\simple_oauth\Entity\Oauth2TokenInterface');
    $creative_token->expects($this->any())
      ->method('get')
      ->willReturnOnConsecutiveCalls($consumer_field, $creative_field);
    $this->creativeAuthUser = new TokenAuthUser($creative_token);

    $organization_field = $this->createMock('Drupal\Core\Field\EntityReferenceFieldItemListInterface');
    $organization_field->expects($this->any())
      ->method('__get')
      ->with('entity')
      ->willReturn($this->organization);
    $organization_token = $this->createMock('Drupal\simple_oauth\Entity\Oauth2TokenInterface');
    $organization_token->expects($this->any())
      ->method('get')
      ->willReturnOnConsecutiveCalls($consumer_field, $organization_field);
    $this->organizationAuthUser = new TokenAuthUser($organization_token);
  }

  /**
   * @covers \Drupal\user_types\Utility\Profile::id
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
   * @covers \Drupal\user_types\Utility\Profile::isCreative
   */
  public function testIsCreative(): void {
    $this->assertTrue(Profile::isCreative($this->creative));
    $this->assertTrue(Profile::isCreative($this->creativeAuthUser));
    $this->assertFalse(Profile::isCreative($this->organization));
    $this->assertFalse(Profile::isCreative($this->organizationAuthUser));
  }

  /**
   * @covers \Drupal\user_types\Utility\Profile::isOrganization
   */
  public function testIsOrganization(): void {
    $this->assertFalse(Profile::isOrganization($this->creative));
    $this->assertFalse(Profile::isOrganization($this->creativeAuthUser));
    $this->assertTrue(Profile::isOrganization($this->organization));
    $this->assertTrue(Profile::isOrganization($this->organizationAuthUser));
  }

}
