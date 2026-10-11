<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\manager\Form\FormOnceGuard;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;

/**
 * Tests the once-only guard for form build ids.
 *
 * @group manager
 */
class FormOnceGuardTest extends MailerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user', 'views', 'logbook', 'manager'];

  /**
   * Gets a guard (a new instance shares the store, like a new request).
   */
  protected function guard(): FormOnceGuard {
    return new FormOnceGuard($this->container->get('keyvalue.expirable'));
  }

  /**
   * The first claim wins, later claims of the same id fail.
   */
  public function testClaimIsOnlyGrantedOnce(): void {
    $this->assertTrue($this->guard()->claim('form-a'));
    $this->assertFalse($this->guard()->claim('form-a'));
    $this->assertFalse($this->guard()->claim('form-a'));
    // Other ids are independent.
    $this->assertTrue($this->guard()->claim('form-b'));
  }

  /**
   * A released claim can be taken again (retry after a failure).
   */
  public function testReleasedClaimCanBeClaimedAgain(): void {
    $guard = $this->guard();
    $this->assertTrue($guard->claim('form-a'));
    $guard->release('form-a');
    $this->assertTrue($this->guard()->claim('form-a'));
    $this->assertFalse($this->guard()->claim('form-a'));
    // Releasing an unknown id is harmless.
    $guard->release('unknown');
  }

  /**
   * Gets a form state that carries the posted input.
   */
  protected function stateWith(array $input): FormState {
    return (new FormState())->setUserInput($input);
  }

  /**
   * The posted build id is only accepted if it is a well-formed string.
   */
  public function testPostedBuildId(): void {
    $this->assertSame('form-abc_DEF-1', $this->guard()->postedBuildId($this->stateWith(['form_build_id' => 'form-abc_DEF-1'])));
    $bad_ids = ['', NULL, ['form-a'], 5, 'abc', 'form-' . str_repeat('a', 101)];
    foreach ([[], ...array_map(static fn ($id) => ['form_build_id' => $id], $bad_ids)] as $input) {
      $this->assertNull($this->guard()->postedBuildId($this->stateWith($input)), json_encode($input));
    }
  }

  /**
   * An action runs once per posted build id and keeps the claim on success.
   */
  public function testRunOnceRunsOnceAndKeepsTheClaim(): void {
    $runs = 0;
    $action = function () use (&$runs): bool {
      $runs++;
      return TRUE;
    };
    $state = $this->stateWith(['form_build_id' => 'form-once']);
    $this->assertTrue($this->guard()->runOnce($state, $action));
    $this->assertNull($this->guard()->runOnce($state, $action));
    $this->assertSame(1, $runs);
  }

  /**
   * Without a usable form_build_id the action does not run (fail closed).
   */
  public function testRunOnceFailsClosedWithoutBuildId(): void {
    $runs = 0;
    $action = function () use (&$runs): bool {
      $runs++;
      return TRUE;
    };
    foreach ([[], ['form_build_id' => ''], ['form_build_id' => ['form-a']], ['form_build_id' => 'junk']] as $input) {
      $this->assertNull($this->guard()->runOnce($this->stateWith($input), $action), json_encode($input));
    }
    $this->assertSame(0, $runs);
  }

  /**
   * A failed action releases the claim, so a retry runs.
   */
  public function testRunOnceReleasesTheClaimOnFailure(): void {
    $state = $this->stateWith(['form_build_id' => 'form-retry']);
    $this->assertFalse($this->guard()->runOnce($state, static fn (): bool => FALSE));
    $this->assertTrue($this->guard()->runOnce($state, static fn (): bool => TRUE));
    $this->assertNull($this->guard()->runOnce($state, static fn (): bool => TRUE));
  }

  /**
   * An exception releases the claim and propagates.
   */
  public function testRunOnceReleasesTheClaimOnException(): void {
    $state = $this->stateWith(['form_build_id' => 'form-throw']);
    try {
      $this->guard()->runOnce($state, static function (): bool {
        throw new \RuntimeException('boom');
      });
      $this->fail('The exception must propagate.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('boom', $exception->getMessage());
    }
    $this->assertTrue($this->guard()->runOnce($state, static fn (): bool => TRUE), 'The claim was released.');
  }

  /**
   * The service is registered.
   */
  public function testServiceExists(): void {
    $this->assertInstanceOf(FormOnceGuard::class, $this->container->get('manager.form_once'));
  }

}
