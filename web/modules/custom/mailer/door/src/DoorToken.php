<?php

namespace Drupal\mailer_door;

/**
 * The signed facts of a door, parsed from its token.
 *
 * The facts are not verified yet: DoorLinks::verify() checks the MAC over the
 * payload with the key of the loaded recipient.
 */
final readonly class DoorToken {

  /**
   * Constructs a DoorToken object.
   *
   * @param int $uid
   *   The user ID of the recipient.
   * @param string $correspondence
   *   The UUID of the correspondence entry of the mail.
   * @param \Drupal\mailer_door\DoorTarget $target
   *   Where the door leads.
   * @param int $timestamp
   *   When the door was made.
   * @param string $subject
   *   The creative UUID of the applicant target, or '' if none.
   * @param string $payload
   *   The signed payload bytes.
   * @param string $mac
   *   The MAC bytes of the token.
   */
  public function __construct(
    public int $uid,
    public string $correspondence,
    public DoorTarget $target,
    public int $timestamp,
    public string $subject,
    public string $payload,
    public string $mac,
  ) {}

}
