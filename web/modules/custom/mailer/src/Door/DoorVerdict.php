<?php

namespace Drupal\mailer\Door;

/**
 * What a door does for a recipient and a visitor.
 *
 * A rejected door reveals nothing, so all its flags are FALSE.
 */
final class DoorVerdict {

  /**
   * Constructs a DoorVerdict object.
   *
   * @param string|null $rejected
   *   Why the recipient has no door, or NULL.
   * @param bool $expired
   *   Whether the door is older than its lifetime or from the future.
   * @param bool $belongs
   *   Whether the project belongs to the recipient.
   * @param bool $staff
   *   Whether the recipient may access the site.
   * @param bool $prospect
   *   Whether the recipient is a prospect organization.
   * @param bool $login
   *   Whether the button logs the recipient in.
   * @param bool $password
   *   Whether the door asks the prospect for a password.
   * @param bool $spent
   *   Whether the draft door of a prospect leads to the hub login.
   * @param bool $follow
   *   Whether pressing the button logs the follow.
   * @param bool $otherSignedIn
   *   Whether the visitor is signed in to another account.
   */
  public function __construct(
    public readonly ?string $rejected,
    public readonly bool $expired,
    public readonly bool $belongs,
    public readonly bool $staff,
    public readonly bool $prospect,
    public readonly bool $login,
    public readonly bool $password,
    public readonly bool $spent,
    public readonly bool $follow,
    public readonly bool $otherSignedIn,
  ) {}

}
