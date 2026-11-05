<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Site\Settings;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Door\DoorLinks;
use Drupal\mailer\Door\DoorTarget;
use Drupal\mailer\HubUrls;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the signed mail door links.
 */
#[CoversClass(DoorLinks::class)]
#[Group('mailer')]
class DoorLinksTest extends UnitTestCase {

  /**
   * The current time of the mocked clock.
   */
  protected const int NOW = 1800000000;

  /**
   * Sets the hash salt and a container with a URL generator stub.
   */
  protected function setUp(): void {
    parent::setUp();
    new Settings(['hash_salt' => 'salt']);
    $url_generator = $this->createMock(UrlGeneratorInterface::class);
    $url_generator->method('generateFromRoute')
      ->willReturnCallback(fn (string $route, array $parameters) => 'https://backend.example.org/' . $route . '/' . implode('/', $parameters));
    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);
  }

  /**
   * The values that were hashed are valid, and build() carries them.
   */
  public function testValidHash(): void {
    $links = $this->links();
    $recipient = $this->recipient();
    $hash = $links->hash($recipient, 'corr', DoorTarget::Applicant, 'creative', static::NOW - 100);
    $this->assertTrue($links->isValid($recipient, 'corr', DoorTarget::Applicant, 'creative', static::NOW - 100, $hash));
    $url = $links->build($recipient, 'corr', DoorTarget::Applicant, 'creative', static::NOW - 100);
    $this->assertStringContainsString('/7/corr/applicant/' . (static::NOW - 100) . '/' . $hash . '/creative', $url);
    $default = $links->build($recipient, 'corr', DoorTarget::Draft);
    $this->assertStringContainsString('/draft/' . static::NOW . '/' . $links->hash($recipient, 'corr', DoorTarget::Draft, '', static::NOW), $default);
  }

  /**
   * Changing any signed part invalidates the hash.
   */
  public function testTamperedPartsAreInvalid(): void {
    $links = $this->links();
    $recipient = $this->recipient();
    $timestamp = static::NOW - 100;
    $hash = $links->hash($recipient, 'corr', DoorTarget::Draft, 'subject', $timestamp);
    $this->assertFalse($links->isValid($this->recipient(8), 'corr', DoorTarget::Draft, 'subject', $timestamp, $hash));
    $this->assertFalse($links->isValid($recipient, 'other', DoorTarget::Draft, 'subject', $timestamp, $hash));
    $this->assertFalse($links->isValid($recipient, 'corr', DoorTarget::Edit, 'subject', $timestamp, $hash));
    $this->assertFalse($links->isValid($recipient, 'corr', DoorTarget::Draft, 'other', $timestamp, $hash));
    $this->assertFalse($links->isValid($recipient, 'corr', DoorTarget::Draft, 'subject', $timestamp + 1, $hash));
  }

  /**
   * Expired links and links from the future are invalid.
   */
  public function testExpiryAndFuture(): void {
    $links = $this->links();
    $recipient = $this->recipient();
    $expired = static::NOW - DoorLinks::LIFETIME - 1;
    $hash = $links->hash($recipient, 'corr', DoorTarget::Draft, '', $expired);
    $this->assertFalse($links->isValid($recipient, 'corr', DoorTarget::Draft, '', $expired, $hash));
    $edge = static::NOW - DoorLinks::LIFETIME;
    $hash = $links->hash($recipient, 'corr', DoorTarget::Draft, '', $edge);
    $this->assertTrue($links->isValid($recipient, 'corr', DoorTarget::Draft, '', $edge, $hash));
    $future = static::NOW + 10;
    $hash = $links->hash($recipient, 'corr', DoorTarget::Draft, '', $future);
    $this->assertFalse($links->isValid($recipient, 'corr', DoorTarget::Draft, '', $future, $hash));
  }

  /**
   * A password change invalidates the link.
   */
  public function testPasswordChangeInvalidates(): void {
    $links = $this->links();
    $hash = $links->hash($this->recipient(), 'corr', DoorTarget::Draft, '', static::NOW);
    $changed = $this->recipient(password: 'new-hash');
    $this->assertFalse($links->isValid($changed, 'corr', DoorTarget::Draft, '', static::NOW, $hash));
  }

  /**
   * A link logs in only until the next login, and never staff.
   */
  public function testLoginOnlyBeforeNextLogin(): void {
    $links = $this->links();
    $timestamp = static::NOW - 100;
    $this->assertTrue($links->allowsLogin($this->recipient(last_login: $timestamp - 1), $timestamp));
    $this->assertTrue($links->allowsLogin($this->recipient(last_login: $timestamp), $timestamp));
    $this->assertFalse($links->allowsLogin($this->recipient(last_login: $timestamp + 1), $timestamp));
    $this->assertTrue($links->allowsLogin($this->recipient(last_login: 0), $timestamp));
    $this->assertFalse($links->allowsLogin($this->recipient(last_login: 0, staff: TRUE), $timestamp));
  }

  /**
   * Targets resolve from the project state, the recipient and the settings.
   */
  public function testTargetUrls(): void {
    $links = $this->links();
    $recipient = $this->recipient();
    $draft = $this->project(TRUE);
    $submitted = $this->project(FALSE);
    $this->assertSame('https://hub.example.org/draft/abc', $links->targetUrl(DoorTarget::Draft, $draft, $recipient, ''));
    $this->assertSame('https://hub.example.org/projects/abc', $links->targetUrl(DoorTarget::Draft, $submitted, $recipient, ''));
    foreach ([DoorTarget::Applicants, DoorTarget::Edit, DoorTarget::Complete] as $target) {
      $this->assertSame('https://hub.example.org/projects/abc', $links->targetUrl($target, $submitted, $recipient, ''));
    }
    $this->assertSame('https://hub.example.org/users/creative-uuid', $links->targetUrl(DoorTarget::Applicant, $submitted, $recipient, 'creative-uuid'));
    $organization = $this->createMock(Organization::class);
    $creative = $this->createMock(Creative::class);
    $this->assertSame('https://youvo.example.org/feedback/organization/abc', $links->targetUrl(DoorTarget::Feedback, $submitted, $organization, ''));
    $this->assertSame('https://youvo.example.org/feedback/creative/abc', $links->targetUrl(DoorTarget::Feedback, $submitted, $creative, ''));
    $this->assertSame('https://hub.example.org/auth/login', $links->loginUrl());

    $local = $this->links(TRUE);
    $this->assertSame('http://localhost:3000/draft/abc', $local->targetUrl(DoorTarget::Draft, $draft, $recipient, ''));
    $this->assertSame('http://localhost:3000/users/creative-uuid', $local->targetUrl(DoorTarget::Applicant, $submitted, $recipient, 'creative-uuid'));
    $this->assertSame('http://localhost:3000/auth/login', $local->loginUrl());
  }

  /**
   * Creates the door links service with stubbed config and clock.
   */
  protected function links(bool $local = FALSE): DoorLinks {
    $config_factory = $this->getConfigFactoryStub([
      'youvo.settings' => [
        'hub_url' => 'https://hub.example.org/',
        'website_url' => 'https://youvo.example.org/',
      ],
      'oauth_grant.settings' => [
        'local' => $local,
        'auth_success_redirect' => '/auth/login',
      ],
    ]);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(static::NOW);
    $time->method('getCurrentTime')->willReturn(static::NOW);
    return new DoorLinks(new HubUrls($config_factory), $config_factory, $time);
  }

  /**
   * Creates a recipient mock.
   */
  protected function recipient(int $uid = 7, string $password = 'old-hash', int $last_login = 0, bool $staff = FALSE): UserInterface {
    $recipient = $this->createMock(UserInterface::class);
    $recipient->method('id')->willReturn($uid);
    $recipient->method('getPassword')->willReturn($password);
    $recipient->method('getLastLoginTime')->willReturn($last_login);
    $recipient->method('hasPermission')->willReturnCallback(fn (string $permission) => $staff && $permission === 'access site');
    return $recipient;
  }

  /**
   * Creates a project mock with the UUID abc.
   */
  protected function project(bool $draft): ProjectInterface {
    $lifecycle = $this->createMock(ProjectLifecycleInterface::class);
    $lifecycle->method('isDraft')->willReturn($draft);
    $project = $this->createMock(ProjectInterface::class);
    $project->method('lifecycle')->willReturn($lifecycle);
    $project->method('uuid')->willReturn('abc');
    return $project;
  }

}
