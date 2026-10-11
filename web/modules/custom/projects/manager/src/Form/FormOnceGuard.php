<?php

namespace Drupal\manager\Form;

use Drupal\Core\Form\FormStateInterface;
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
 *
 * The guard fails closed: a submission without a usable posted form_build_id
 * is treated like a replay, because the form API would otherwise build the form
 * with a fresh random build id that was never claimed.
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

  /**
   * Gets the form_build_id the browser posted.
   *
   * @return string|null
   *   The id, or NULL if it is missing, not a string or not shaped like a
   *   Drupal form build id.
   */
  public function postedBuildId(FormStateInterface $form_state): ?string {
    $id = $form_state->getUserInput()['form_build_id'] ?? NULL;
    return is_string($id) && preg_match('/^form-[A-Za-z0-9_-]{1,100}$/', $id) ? $id : NULL;
  }

  /**
   * Runs an action once per posted form build id.
   *
   * The id is claimed before the action runs. The claim is kept after a
   * success and released if the action reports a failure or throws, so that
   * a retry with the same form works.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the submission.
   * @param callable $action
   *   The state-changing action; returns TRUE on success.
   *
   * @return bool|null
   *   The result of the action, or NULL if the action did not run because the
   *   form_build_id is missing or invalid or was already used.
   */
  public function runOnce(FormStateInterface $form_state, callable $action): ?bool {
    $build_id = $this->postedBuildId($form_state);
    if ($build_id === NULL || !$this->claim($build_id)) {
      return NULL;
    }
    $succeeded = FALSE;
    try {
      $succeeded = (bool) $action();
    }
    finally {
      if (!$succeeded) {
        $this->release($build_id);
      }
    }
    return $succeeded;
  }

}
