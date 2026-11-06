<?php

namespace Drupal\mailer_door;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;

/**
 * Builds and verifies signed mail door links.
 *
 * A door is /go/{token}: the signed facts packed into bytes, followed by a
 * truncated HMAC, all base64url-encoded.
 *
 * Like core one-time login links, the signature includes the password hash,
 * so a password change invalidates every door of that recipient.
 */
class DoorLinks {

  /**
   * How long a door stays valid, in seconds (30 days).
   */
  const int LIFETIME = 2592000;

  /**
   * The version of the token format.
   */
  const int VERSION = 1;

  /**
   * The offset of the uid in the payload, after the version byte.
   */
  const int UID_OFFSET = 1;

  /**
   * The offset of the correspondence UUID in the payload, after the uid.
   */
  const int CORRESPONDENCE_OFFSET = self::UID_OFFSET + 4;

  /**
   * The length of a UUID in the payload, in bytes.
   */
  const int UUID_LENGTH = 16;

  /**
   * The offset of the target code in the payload, after the correspondence.
   */
  const int TARGET_OFFSET = self::CORRESPONDENCE_OFFSET + self::UUID_LENGTH;

  /**
   * The length of the payload without a subject, in bytes.
   *
   * The target code and the timestamp end it; a subject UUID may follow.
   */
  const int PAYLOAD_LENGTH = self::TARGET_OFFSET + 5;

  /**
   * The length of the MAC, in bytes (128 bits).
   */
  const int MAC_LENGTH = 16;

  /**
   * Constructs a DoorLinks object.
   */
  public function __construct(
    protected HubUrls $hubUrls,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
  ) {}

  /**
   * Builds the absolute URL of a door.
   *
   * @throws \InvalidArgumentException
   *   When the correspondence or a given subject is no lowercase UUID, or the
   *   uid or the timestamp does not fit into 32 bits.
   */
  public function build(UserInterface $recipient, string $correspondence, DoorTarget $target, string $subject = '', ?int $timestamp = NULL): string {
    $timestamp ??= $this->time->getRequestTime();
    $payload = $this->payload((int) $recipient->id(), $correspondence, $target, $timestamp, $subject);
    return Url::fromRoute('mailer_door.go', [
      'token' => $this->encode($payload . $this->mac($recipient, $payload)),
    ], ['absolute' => TRUE])->toString();
  }

  /**
   * Reads the facts of a door from its token.
   *
   * The token is not verified yet, see verify().
   *
   * @return \Drupal\mailer_door\DoorToken|null
   *   The facts, or NULL if the token is malformed.
   */
  public function parse(string $token): ?DoorToken {

    $short = static::PAYLOAD_LENGTH + static::MAC_LENGTH;
    $long = $short + static::UUID_LENGTH;
    $length = strlen($token);
    if ($length !== $this->encodedLength($short) && $length !== $this->encodedLength($long)) {
      return NULL;
    }
    $bytes = $this->decode($token);
    if ($bytes === NULL || (strlen($bytes) !== $short && strlen($bytes) !== $long)) {
      return NULL;
    }

    $payload = substr($bytes, 0, -static::MAC_LENGTH);
    $mac = substr($bytes, -static::MAC_LENGTH);
    $head = unpack('Cversion/Nuid', $payload);
    $tail = unpack('Ccode/Ntimestamp', $payload, static::TARGET_OFFSET);
    if ($head === FALSE || $tail === FALSE || $head['version'] !== static::VERSION) {
      return NULL;
    }
    $target = DoorTarget::fromCode($tail['code']);
    if ($target === NULL) {
      return NULL;
    }

    $subject = strlen($payload) > static::PAYLOAD_LENGTH ? $this->bytesToUuid(substr($payload, static::PAYLOAD_LENGTH, static::UUID_LENGTH)) : '';
    $correspondence = $this->bytesToUuid(substr($payload, static::CORRESPONDENCE_OFFSET, static::UUID_LENGTH));
    return new DoorToken($head['uid'], $correspondence, $target, $tail['timestamp'], $subject, $payload, $mac);
  }

  /**
   * Checks that the recipient signed the token.
   *
   * Only the signature: the verdict decides from the age of the door.
   */
  public function verify(UserInterface $recipient, DoorToken $token): bool {
    return (int) $recipient->id() === $token->uid
      && hash_equals($this->mac($recipient, $token->payload), $token->mac);
  }

  /**
   * Packs the signed facts of a door.
   *
   * @throws \InvalidArgumentException
   *   When the correspondence or a given subject is no lowercase UUID, or the
   *   uid or the timestamp does not fit into 32 bits.
   */
  protected function payload(int $uid, string $correspondence, DoorTarget $target, int $timestamp, string $subject): string {

    // Packing would cut larger numbers silently.
    if ($uid < 0 || $uid > 0xFFFFFFFF || $timestamp < 0 || $timestamp > 0xFFFFFFFF) {
      throw new \InvalidArgumentException('A door needs a uid and a timestamp of 32 bits.');
    }

    $payload = pack('CNa' . static::UUID_LENGTH . 'CN', static::VERSION, $uid, $this->uuidToBytes($correspondence), $target->code(), $timestamp);
    if ($subject !== '') {
      $payload .= $this->uuidToBytes($subject);
    }
    return $payload;
  }

  /**
   * Computes the MAC of a payload, truncated to 128 bits.
   */
  protected function mac(UserInterface $recipient, string $payload): string {
    return substr(hash_hmac('sha256', $payload, Settings::getHashSalt() . $recipient->getPassword(), TRUE), 0, static::MAC_LENGTH);
  }

  /**
   * Gets the length of bytes encoded as base64url without padding.
   */
  protected function encodedLength(int $bytes): int {
    return intdiv(4 * $bytes + 2, 3);
  }

  /**
   * Encodes bytes as base64url without padding.
   */
  protected function encode(string $bytes): string {
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
  }

  /**
   * Decodes base64url without padding, strictly.
   *
   * Only the canonical encoding of the bytes is accepted, so that no two
   * tokens carry the same bytes.
   */
  protected function decode(string $encoded): ?string {
    if (preg_match('/^[A-Za-z0-9_-]*$/D', $encoded) !== 1) {
      return NULL;
    }
    $bytes = base64_decode(strtr($encoded, '-_', '+/'), TRUE);
    if ($bytes === FALSE || $this->encode($bytes) !== $encoded) {
      return NULL;
    }
    return $bytes;
  }

  /**
   * Converts a lowercase canonical UUID to its 16 bytes.
   *
   * @throws \InvalidArgumentException
   *   When the string is no lowercase canonical UUID.
   */
  protected function uuidToBytes(string $uuid): string {
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D', $uuid) !== 1) {
      throw new \InvalidArgumentException('A door needs lowercase canonical UUIDs.');
    }
    return (string) hex2bin(str_replace('-', '', $uuid));
  }

  /**
   * Formats 16 bytes as a lowercase canonical UUID.
   */
  protected function bytesToUuid(string $bytes): string {
    $hex = bin2hex($bytes);
    return implode('-', [
      substr($hex, 0, 8),
      substr($hex, 8, 4),
      substr($hex, 12, 4),
      substr($hex, 16, 4),
      substr($hex, 20, 12),
    ]);
  }

  /**
   * Checks whether a door may log its recipient in.
   *
   * A door logs in only until the recipient logs in again, and never staff.
   */
  public function allowsLogin(UserInterface $recipient, int $timestamp): bool {
    return $timestamp >= (int) $recipient->getLastLoginTime()
      && !$recipient->hasPermission('access site');
  }

  /**
   * Decides what a door does for its recipient and the visitor.
   *
   * The caller checks the signature first. The visitor is NULL when nobody is
   * signed in.
   */
  public function verdict(UserInterface $recipient, ProjectInterface $project, DoorTarget $target, int $timestamp, ?AccountInterface $visitor): DoorVerdict {

    // The anonymous user is blocked too, so it is checked first.
    $rejected = match (TRUE) {
      $recipient->isAnonymous() => 'Kein Konto',
      $recipient->isBlocked() => 'Konto gesperrt',
      default => NULL,
    };
    if ($rejected !== NULL) {
      return new DoorVerdict($rejected, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE);
    }

    $now = $this->time->getRequestTime();
    $expired = $timestamp > $now || $now - $timestamp > static::LIFETIME;

    // The owner of the project, or a creative of a project for feedback.
    $belongs = (int) $project->getOwnerId() === (int) $recipient->id()
      || ($target === DoorTarget::Feedback
        && $recipient instanceof Creative
        && array_key_exists((int) $recipient->id(), $project->getParticipants('Creative')));

    $staff = $recipient->hasPermission('access site');
    $signed_in = $visitor !== NULL && $visitor->isAuthenticated();
    $other_signed_in = $signed_in && (int) $visitor->id() !== (int) $recipient->id();

    $login = !$expired
      && $belongs
      && !$signed_in
      && $target->logsIn()
      && $this->allowsLogin($recipient, $timestamp);

    // A prospect has no password. It is logged in only after it set one on a
    // door to its draft, never by the plain button.
    $prospect = $recipient instanceof Organization && $recipient->hasRoleProspect();
    $password = $login
      && $prospect
      && $target === DoorTarget::Draft
      && $project->lifecycle()->isDraft();
    $login = $login && !$prospect;

    // Without the password form the draft door of a prospect is spent, so it
    // leads to the hub login.
    $spent = $prospect && $target === DoorTarget::Draft && !$password;

    // Staff follow like any recipient, for example a manager who takes part
    // in a project, but are never logged in.
    $follow = !$expired
      && $belongs
      && !$other_signed_in;

    return new DoorVerdict(NULL, $expired, $belongs, $staff, $prospect, $login, $password, $spent, $follow, $other_signed_in);
  }

  /**
   * Resolves where a door leads, from the current project state.
   *
   * The subject is the creative UUID for the applicant target.
   */
  public function targetUrl(DoorTarget $target, ProjectInterface $project, UserInterface $recipient, string $subject): string {
    $base = $this->hubUrls->base();
    return match ($target) {
      DoorTarget::Draft => $this->hubUrls->project($project),
      DoorTarget::Applicants, DoorTarget::Edit, DoorTarget::Complete => $base . '/projects/' . $project->uuid(),
      DoorTarget::Applicant => $base . '/users/' . $subject,
      DoorTarget::Feedback => $this->feedbackUrl($project, $recipient),
    };
  }

  /**
   * Gets the hub page to open after a door logged the recipient in.
   */
  public function loginUrl(): string {
    return $this->hubUrls->base() . $this->configFactory->get('oauth_grant.settings')->get('auth_success_redirect');
  }

  /**
   * Gets the website feedback form for the recipient type.
   */
  protected function feedbackUrl(ProjectInterface $project, UserInterface $recipient): string {
    $website_url = rtrim((string) $this->configFactory->get('youvo.settings')->get('website_url'), '/');
    $type = $recipient instanceof Organization ? 'organization' : 'creative';
    return $website_url . '/feedback/' . $type . '/' . $project->uuid();
  }

}
