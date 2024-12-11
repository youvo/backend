<?php

namespace Drupal\user_types\Utility;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;

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
   * Determines if account is creative.
   */
  public static function isCreative(AccountInterface $account): bool {
    return self::isUserType($account, 'user', 'Drupal\\creatives\\Entity\\Creative');
  }

  /**
   * Determines if account is organization.
   */
  public static function isOrganization(AccountInterface $account): bool {
    return self::isUserType($account, 'organization', 'Drupal\\organizations\\Entity\\Organization');
  }

  /**
   * Determines if account is of a user type.
   *
   * With different authorization methods the account object may be a
   * AccountProxy or a TokenAuthUser. Use this helper to determine whether
   * the account is of a specific user type.
   */
  protected static function isUserType(
    AccountInterface $account,
    string $type,
    string $class,
  ): bool {
    if ($account instanceof AccountProxyInterface) {
      $account = $account->getAccount();
    }
    if (class_exists('Drupal\\simple_oauth\\Authentication\\TokenAuthUser') &&
      get_class($account) == 'Drupal\\simple_oauth\\Authentication\\TokenAuthUser') {
      return $account->bundle() == $type;
    }
    return class_exists($class) && $account instanceof $class;
  }

}
