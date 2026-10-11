<?php

namespace Drupal\manager\Form;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

/**
 * Server-side once-only guard for form submissions, keyed on form_build_id.
 *
 * Drupal does not stop a POST that carries a valid form_token and an old
 * form_build_id (second tab, retry after a lost response, replayed request):
 * the form is rebuilt and submitted again. A state-changing submit handler
 * claims the build id before it acts, releases the claim if the action
 * failed (so a retry works) and keeps it after a success.
 */
class FormOnceGuard {

  /**
   * How long a consumed build id is remembered, in seconds.
   */
  protected const TTL = 3600;

  /**
   * The key value store.
   */
  protected KeyValueStoreExpirableInterface $store;

  /**
   * Constructs the guard.
   */
  public function __construct(KeyValueExpirableFactoryInterface $factory) {
    $this->store = $factory->get('manager_form_once');
  }

  /**
   * Claims a form build id (atomic set-if-not-exists).
   *
   * @return bool
   *   TRUE if the caller got the claim and may act, FALSE if the id was
   *   already claimed by an earlier submission.
   */
  public function claim(string $form_build_id): bool {
    return $this->store->setWithExpireIfNotExists($form_build_id, TRUE, self::TTL);
  }

  /**
   * Releases a claim, for example after a failed action.
   */
  public function release(string $form_build_id): void {
    $this->store->delete($form_build_id);
  }

}
