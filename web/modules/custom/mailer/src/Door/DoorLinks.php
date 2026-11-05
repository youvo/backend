<?php

namespace Drupal\mailer\Door;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\mailer\HubUrls;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\user\UserInterface;

/**
 * Builds and verifies signed mail door links.
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
   * Constructs a DoorLinks object.
   */
  public function __construct(
    protected HubUrls $hubUrls,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
  ) {}

  /**
   * Builds the absolute URL of a door.
   */
  public function build(UserInterface $recipient, string $correspondence, DoorTarget $target, string $subject = '', ?int $timestamp = NULL): string {
    $timestamp ??= $this->time->getRequestTime();
    return Url::fromRoute('mailer.door', [
      'uid' => $recipient->id(),
      'correspondence' => $correspondence,
      'target' => $target->value,
      'timestamp' => $timestamp,
      'hash' => $this->hash($recipient, $correspondence, $target, $subject, $timestamp),
      'subject' => $subject,
    ], ['absolute' => TRUE])->toString();
  }

  /**
   * Computes the signature of a door.
   */
  public function hash(UserInterface $recipient, string $correspondence, DoorTarget $target, string $subject, int $timestamp): string {
    $data = implode(':', [$recipient->id(), $correspondence, $target->value, $subject, $timestamp]);
    return Crypt::hmacBase64($data, Settings::getHashSalt() . $recipient->getPassword());
  }

  /**
   * Checks the signature and the age of a door.
   */
  public function isValid(UserInterface $recipient, string $correspondence, DoorTarget $target, string $subject, int $timestamp, string $hash): bool {
    $now = $this->time->getRequestTime();
    return hash_equals($this->hash($recipient, $correspondence, $target, $subject, $timestamp), $hash)
      && $timestamp <= $now
      && $now - $timestamp <= static::LIFETIME;
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
