<?php

namespace Drupal\Tests\mailer_door\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Site\Settings;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer_door\DoorLinks;
use Drupal\mailer_door\DoorTarget;
use Drupal\mailer_door\HubUrls;
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
#[Group('mailer_door')]
class DoorLinksTest extends UnitTestCase {

  /**
   * The current time of the mocked clock.
   */
  protected const int NOW = 1800000000;

  /**
   * The correspondence UUID of the doors.
   */
  protected const string CORRESPONDENCE = '0f5e3d9a-7c1b-4e2f-9a8d-6b5c4d3e2f1a';

  /**
   * The creative UUID of the applicant doors.
   */
  protected const string SUBJECT = 'c0ffee00-1234-4abc-8def-0123456789ab';

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
   * A door round-trips its facts, with and without a subject.
   */
  public function testRoundTrip(): void {

    $links = $this->links();
    $recipient = $this->recipient();

    $token = $this->token($links->build($recipient, static::CORRESPONDENCE, DoorTarget::Applicant, static::SUBJECT, static::NOW - 100));
    $this->assertSame(78, strlen($token));
    $door = $links->parse($token);
    $this->assertNotNull($door);
    $this->assertSame(7, $door->uid);
    $this->assertSame(static::CORRESPONDENCE, $door->correspondence);
    $this->assertSame(DoorTarget::Applicant, $door->target);
    $this->assertSame(static::NOW - 100, $door->timestamp);
    $this->assertSame(static::SUBJECT, $door->subject);
    $this->assertSame(42, strlen($door->payload));
    $this->assertSame(16, strlen($door->mac));
    $this->assertTrue($links->verify($recipient, $door));

    // The timestamp defaults to the request time.
    $url = $links->build($recipient, static::CORRESPONDENCE, DoorTarget::Draft);
    $this->assertStringStartsWith('https://backend.example.org/mailer_door.go/', $url);
    $token = $this->token($url);
    $this->assertSame(56, strlen($token));
    $door = $links->parse($token);
    $this->assertNotNull($door);
    $this->assertSame(DoorTarget::Draft, $door->target);
    $this->assertSame(static::NOW, $door->timestamp);
    $this->assertSame('', $door->subject);
    $this->assertTrue($links->verify($recipient, $door));

    // Each target keeps its code.
    foreach (DoorTarget::cases() as $target) {
      $this->assertSame($target, $links->parse($this->token($links->build($recipient, static::CORRESPONDENCE, $target)))?->target);
    }
  }

  /**
   * Every changed byte or character fails to parse or to verify.
   */
  public function testEveryFlipFails(): void {

    $links = $this->links();
    $recipient = $this->recipient();
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    foreach (['', static::SUBJECT] as $subject) {
      $token = $this->token($links->build($recipient, static::CORRESPONDENCE, DoorTarget::Applicant, $subject, static::NOW - 100));
      $bytes = $this->decode($token);
      for ($i = 0; $i < strlen($bytes); $i++) {
        foreach ([0x01, 0x80, 0xff] as $mask) {
          $flipped = $bytes;
          $flipped[$i] = chr(ord($flipped[$i]) ^ $mask);
          $this->assertFalse($this->accepts($links, $recipient, $this->encode($flipped)), "Byte $i with mask $mask.");
        }
      }
      // Characters too: the last character of 78 carries unused bits.
      for ($i = 0; $i < strlen($token); $i++) {
        foreach (str_split($alphabet) as $character) {
          if ($character === $token[$i]) {
            continue;
          }
          $changed = substr_replace($token, $character, $i, 1);
          $this->assertFalse($this->accepts($links, $recipient, $changed), "Character $i as $character.");
        }
      }
    }
  }

  /**
   * Malformed tokens give NULL.
   */
  public function testMalformedTokens(): void {

    $links = $this->links();
    $recipient = $this->recipient();
    $short = $this->token($links->build($recipient, static::CORRESPONDENCE, DoorTarget::Draft));
    $long = $this->token($links->build($recipient, static::CORRESPONDENCE, DoorTarget::Applicant, static::SUBJECT));
    $bytes = $this->decode($short);

    // Wrong lengths, of the token and of the bytes.
    $this->assertNull($links->parse(''));
    $this->assertNull($links->parse(substr($short, 0, -1)));
    $this->assertNull($links->parse($short . 'A'));
    $this->assertNull($links->parse(substr($long, 0, -1)));
    $this->assertNull($links->parse($long . 'A'));
    $this->assertNull($links->parse($this->encode($bytes . 'x')));
    $this->assertNull($links->parse($this->encode(substr($bytes, 0, -1))));
    $this->assertNull($links->parse($this->encode($bytes . str_repeat('x', 15))));

    // A wrong version or an unknown target code.
    foreach ([0, 2, 255] as $version) {
      $this->assertNull($links->parse($this->encode(chr($version) . substr($bytes, 1))), "Version $version.");
    }
    foreach ([0, 7, 255] as $code) {
      $this->assertNull($links->parse($this->encode(substr_replace($bytes, chr($code), 21, 1))), "Code $code.");
    }

    // Padding and characters outside base64url.
    $this->assertNull($links->parse(substr($long, 0, -2) . '=='));
    $this->assertNull($links->parse($long . '=='));
    foreach (['+', '/', '=', '.', ' ', '%'] as $character) {
      $this->assertNull($links->parse(substr_replace($short, $character, 10, 1)), "Character $character.");
    }
    $this->assertNull($links->parse(substr($short, 0, -1) . "\n"));
    $this->assertNull($links->parse($short . "\n"));
  }

  /**
   * A door needs lowercase canonical UUIDs.
   */
  public function testBuildRejectsNonUuids(): void {
    $links = $this->links();
    $recipient = $this->recipient();
    $rejected = 0;
    $cases = [
      ['corr', ''],
      [strtoupper(static::CORRESPONDENCE), ''],
      [static::CORRESPONDENCE, 'creative'],
    ];
    foreach ($cases as [$correspondence, $subject]) {
      try {
        $links->build($recipient, $correspondence, DoorTarget::Applicant, $subject);
        $this->fail('A door was built for ' . $correspondence . ' / ' . $subject);
      }
      catch (\InvalidArgumentException) {
        $rejected++;
      }
    }
    $this->assertSame(3, $rejected);
  }

  /**
   * A password change or another recipient fails to verify.
   */
  public function testPasswordChangeFailsVerify(): void {
    $links = $this->links();
    $door = $links->parse($this->token($links->build($this->recipient(), static::CORRESPONDENCE, DoorTarget::Draft)));
    $this->assertNotNull($door);
    $this->assertTrue($links->verify($this->recipient(), $door));
    $this->assertFalse($links->verify($this->recipient(password: 'new-hash'), $door));
    $this->assertFalse($links->verify($this->recipient(8), $door));
  }

  /**
   * The MAC covers the uid: another uid fails with the same password hash.
   *
   * The uid of the token matches the recipient, so only the MAC rejects it.
   */
  public function testMacCoversUid(): void {
    $links = $this->links();
    $token = $this->token($links->build($this->recipient(), static::CORRESPONDENCE, DoorTarget::Draft));
    $bytes = $this->decode($token);
    $tampered = $this->encode(substr_replace($bytes, pack('N', 8), DoorLinks::UID_OFFSET, 4));
    $door = $links->parse($tampered);
    $this->assertNotNull($door);
    $this->assertSame(8, $door->uid);
    $this->assertSame(substr($bytes, -DoorLinks::MAC_LENGTH), $door->mac);
    $other = $this->recipient(8);
    $this->assertSame(8, (int) $other->id());
    $this->assertSame($this->recipient()->getPassword(), $other->getPassword());
    $this->assertFalse($links->verify($other, $door));
  }

  /**
   * A door needs a uid and a timestamp that fit into 32 bits.
   */
  public function testBuildRejectsNumbersBeyond32Bits(): void {
    $links = $this->links();
    $rejected = 0;
    $cases = [
      [-1, static::NOW],
      [0x100000000, static::NOW],
      [7, -1],
      [7, 0x100000000],
    ];
    foreach ($cases as [$uid, $timestamp]) {
      try {
        $links->build($this->recipient($uid), static::CORRESPONDENCE, DoorTarget::Draft, '', $timestamp);
        $this->fail('A door was built for uid ' . $uid . ' at ' . $timestamp);
      }
      catch (\InvalidArgumentException) {
        $rejected++;
      }
    }
    $this->assertSame(4, $rejected);
    $this->assertNotNull($links->parse($this->token($links->build($this->recipient(0xFFFFFFFF), static::CORRESPONDENCE, DoorTarget::Draft, '', 0xFFFFFFFF))));
  }

  /**
   * Doors expire after their lifetime, and doors from the future are expired.
   */
  public function testExpiry(): void {
    $links = $this->links();
    $recipient = $this->recipient();
    $project = $this->project(FALSE);
    $this->assertFalse($links->verdict($recipient, $project, DoorTarget::Draft, static::NOW, NULL)->expired);
    $this->assertFalse($links->verdict($recipient, $project, DoorTarget::Draft, static::NOW - DoorLinks::LIFETIME, NULL)->expired);
    $this->assertTrue($links->verdict($recipient, $project, DoorTarget::Draft, static::NOW - DoorLinks::LIFETIME - 1, NULL)->expired);
    $this->assertTrue($links->verdict($recipient, $project, DoorTarget::Draft, static::NOW + 10, NULL)->expired);
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
   * A hub target goes to the login as its path, any other target is dropped.
   */
  public function testLoginUrlCarriesHubPath(): void {

    $links = $this->links();
    $url = $links->loginUrl('https://hub.example.org/projects/abc');
    $this->assertSame('/projects/abc', $this->redirectOf($url, 'https://hub.example.org/auth/login'));
    $this->assertSame('/draft/abc?tab=1', $this->redirectOf($links->loginUrl('https://hub.example.org/draft/abc?tab=1#top'), 'https://hub.example.org/auth/login'));
    $this->assertSame('/', $this->redirectOf($links->loginUrl('https://hub.example.org'), 'https://hub.example.org/auth/login'));

    // Website targets, look-alike hosts, other schemes and no target.
    foreach ([
      NULL,
      'https://youvo.example.org/feedback/organization/abc',
      'https://hub.example.org.evil.example/projects/abc',
      'https://hub.example.orgx/projects/abc',
      'http://hub.example.org/projects/abc',
      'https://hub.example.org@evil.example/projects/abc',
      'https://hub.example.org//evil.example/projects',
    ] as $target) {
      $this->assertSame('https://hub.example.org/auth/login', $links->loginUrl($target), (string) $target);
    }

    // The local hub, and a look-alike port.
    $local = $this->links(TRUE);
    $this->assertSame('/users/abc', $this->redirectOf($local->loginUrl('http://localhost:3000/users/abc'), 'http://localhost:3000/auth/login'));
    $this->assertSame('http://localhost:3000/auth/login', $local->loginUrl('http://localhost:30000/users/abc'));

    // A hub base with a path keeps it in the redirect path.
    $nested = $this->links(hub_url: 'https://example.org/hub/');
    $this->assertSame('/hub/projects/abc', $this->redirectOf($nested->loginUrl('https://example.org/hub/projects/abc'), 'https://example.org/hub/auth/login'));
    $this->assertSame('https://example.org/hub/auth/login', $nested->loginUrl('https://example.org/hubx/projects/abc'));
    $this->assertSame('https://example.org/hub/auth/login', $nested->loginUrl('https://example.org/projects/abc'));

    // A query of the login is kept.
    $query = $this->links(login: '/auth/login?source=door');
    $url = $query->loginUrl('https://hub.example.org/projects/abc');
    $this->assertSame('/projects/abc', $this->redirectOf($url, 'https://hub.example.org/auth/login'));
    parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);
    $this->assertSame('door', $parameters['source']);
  }

  /**
   * Gets the redirect path of a login URL and checks the rest of it.
   */
  protected function redirectOf(string $url, string $login): string {
    $this->assertSame($login, strtok($url, '?'));
    $this->assertSame(1, substr_count($url, 'redirect='));
    parse_str((string) parse_url($url, PHP_URL_QUERY), $parameters);
    $this->assertIsString($parameters['redirect']);
    return $parameters['redirect'];
  }

  /**
   * Gets the token of a door URL.
   */
  protected function token(string $url): string {
    $parts = explode('/', $url);
    return (string) end($parts);
  }

  /**
   * Checks whether a token parses and verifies for the recipient.
   */
  protected function accepts(DoorLinks $links, UserInterface $recipient, string $token): bool {
    $door = $links->parse($token);
    return $door !== NULL && $links->verify($recipient, $door);
  }

  /**
   * Encodes bytes as base64url without padding.
   */
  protected function encode(string $bytes): string {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
  }

  /**
   * Decodes base64url without padding.
   */
  protected function decode(string $token): string {
    $bytes = base64_decode(strtr($token, '-_', '+/'), TRUE);
    $this->assertIsString($bytes);
    return $bytes;
  }

  /**
   * Creates the door links service with stubbed config and clock.
   */
  protected function links(bool $local = FALSE, string $hub_url = 'https://hub.example.org/', string $login = '/auth/login'): DoorLinks {
    $config_factory = $this->getConfigFactoryStub([
      'youvo.settings' => [
        'hub_url' => $hub_url,
        'website_url' => 'https://youvo.example.org/',
      ],
      'oauth_grant.settings' => [
        'local' => $local,
        'auth_success_redirect' => $login,
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
