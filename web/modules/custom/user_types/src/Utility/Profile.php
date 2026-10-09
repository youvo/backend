<?php

namespace Drupal\user_types\Utility;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\simple_oauth\Authentication\TokenAuthUserInterface;

/**
 * Utility class to determine account types by different account objects.
 */
final class Profile {

  /**
   * Gets UID of an account.
   */
  public static function id(AccountInterface|int $account): int {
    return $account instanceof AccountInterface ? $account->id() : $account;
  }

  /**
   * Gets the actual account object.
   *
   * With different authorization methods the account object may be an
   * AccountProxy or a TokenAuthUser, which decorates the user entity. Use this
   * helper to get the underlying account object, for example a Creative or an
   * Organization entity.
   */
  public static function account(AccountInterface $account): AccountInterface {
    if ($account instanceof AccountProxyInterface) {
      $account = $account->getAccount();
    }
    if ($account instanceof TokenAuthUserInterface) {
      $account = $account->getSubject();
    }
    return $account;
  }

  /**
   * Determines if account is creative.
   */
  public static function isCreative(AccountInterface $account): bool {
    return static::account($account) instanceof Creative;
  }

  /**
   * Determines if account is organization.
   */
  public static function isOrganization(AccountInterface $account): bool {
    return static::account($account) instanceof Organization;
  }

}
