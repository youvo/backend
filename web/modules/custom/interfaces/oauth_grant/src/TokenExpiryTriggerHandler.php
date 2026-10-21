<?php

namespace Drupal\oauth_grant;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\simple_oauth\TokenExpiryTriggerHandlerInterface;
use Drupal\user\UserInterface;

/**
 * Revokes tokens on user updates only for security relevant changes.
 *
 * By default, simple_oauth revokes the tokens of a user on every update of the
 * user entity, which logs out the user when updating the profile. We only
 * revoke the tokens if the password, the roles or the status changed.
 */
class TokenExpiryTriggerHandler implements TokenExpiryTriggerHandlerInterface {

  public function __construct(
    protected TokenExpiryTriggerHandlerInterface $inner,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handleCron(): void {
    $this->inner->handleCron();
  }

  /**
   * {@inheritdoc}
   */
  public function handleUserUpdate(UserInterface $user): void {
    if ($this->isSecurityRelevantChange($user)) {
      $this->inner->handleUserUpdate($user);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleConsumerUpdate(ConsumerInterface $consumer): void {
    $this->inner->handleConsumerUpdate($consumer);
  }

  /**
   * {@inheritdoc}
   */
  public function handleConsumerDelete(ConsumerInterface $consumer): void {
    $this->inner->handleConsumerDelete($consumer);
  }

  /**
   * Checks if the password, the roles or the status of the user changed.
   */
  protected function isSecurityRelevantChange(UserInterface $user): bool {
    $original = $user->original ?? NULL;
    // Without the original we can not tell what changed. Stay on the safe side.
    if (!$original instanceof UserInterface) {
      return TRUE;
    }

    $roles = $user->getRoles(TRUE);
    $original_roles = $original->getRoles(TRUE);
    sort($roles);
    sort($original_roles);

    return $user->getPassword() !== $original->getPassword()
      || $user->isBlocked() !== $original->isBlocked()
      || $roles !== $original_roles;
  }

}
