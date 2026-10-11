<?php

namespace Drupal\Tests\manager\Kernel;

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
   * The service is registered.
   */
  public function testServiceExists(): void {
    $this->assertInstanceOf(FormOnceGuard::class, $this->container->get('manager.form_once'));
  }

}
