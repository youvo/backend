<?php

namespace Drupal\user_types\Utility;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Utility class to determine account types by different account objects.
 */
final class Profile {

  /**
   * Helper to get uid of an account.
   */
  public static function id(AccountInterface|int $account) {
    return $account instanceof AccountInterface ? $account->id() : $account;
  }

  /**
   * Helper to determine if account is creative.
   */
  public static function isCreative(AccountInterface $account) {
    return self::isUserType($account, 'user', 'Drupal\\creatives\\Entity\\Creative');
  }

  /**
   * Helper to determine if account is organization.
   */
  public static function isOrganization(AccountInterface $account) {
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
    string $class
  ) {
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
