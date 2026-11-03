<?php

namespace Drupal\Tests\manager\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\KeyValueStore\MemoryStorage;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\manager\Form\FormOnceGuard;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the once-only guard for form build ids.
 */
#[CoversClass(FormOnceGuard::class)]
#[Group('manager')]
class FormOnceGuardTest extends UnitTestCase {

  /**
   * The store shared by all guards of a test, like the database.
   */
  protected KeyValueStoreExpirableInterface $store;

  /**
   * The lock names acquired and released, in order.
   *
   * @var string[]
   */
  protected array $lockCalls = [];

  /**
   * The lock names another request holds.
   *
   * @var string[]
   */
  protected array $held = [];

  /**
   * The number of runs of the action.
   */
  protected int $runs = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->store = new class('manager_form_once') extends MemoryStorage implements KeyValueStoreExpirableInterface {

      /**
       * {@inheritdoc}
       */
      public function setWithExpire($key, $value, $expire): void {
        $this->set($key, $value);
      }

      /**
       * {@inheritdoc}
       */
      public function setWithExpireIfNotExists($key, $value, $expire): bool {
        return $this->setIfNotExists($key, $value);
      }

      /**
       * {@inheritdoc}
       */
      public function setMultipleWithExpire(array $data, $expire): void {
        $this->setMultiple($data);
      }

    };
  }

  /**
   * Gets a guard (a new instance shares the store, like a new request).
   */
  protected function guard(): FormOnceGuard {
    $factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $factory->method('get')->with('manager_form_once')->willReturn($this->store);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(function (string $name): bool {
      $this->lockCalls[] = 'acquire ' . $name;
      return !in_array($name, $this->held, TRUE);
    });
    $lock->method('release')->willReturnCallback(function (string $name): void {
      $this->lockCalls[] = 'release ' . $name;
    });
    return new FormOnceGuard($factory, $lock);
  }

  /**
   * Gets a form state that carries the posted input.
   */
  protected function stateWith(array $input): FormState {
    return (new FormState())->setUserInput($input);
  }

  /**
   * The first claim wins; a released claim can be taken again.
   */
  public function testClaim(): void {

    $this->assertTrue($this->guard()->claim('form-a'));
    $this->assertFalse($this->guard()->claim('form-a'));
    // Other ids are independent.
    $this->assertTrue($this->guard()->claim('form-b'));

    // A released claim can be taken again (retry after a failure).
    $this->guard()->release('form-a');
    $this->assertTrue($this->guard()->claim('form-a'));
    $this->assertFalse($this->guard()->claim('form-a'));
    // Releasing an unknown id is harmless.
    $this->guard()->release('unknown');

    // Every claim ran under the lock of its build id and released it.
    $this->assertSame([
      'acquire manager_form_once:form-a', 'release manager_form_once:form-a',
      'acquire manager_form_once:form-a', 'release manager_form_once:form-a',
      'acquire manager_form_once:form-b', 'release manager_form_once:form-b',
      'acquire manager_form_once:form-a', 'release manager_form_once:form-a',
      'acquire manager_form_once:form-a', 'release manager_form_once:form-a',
    ], $this->lockCalls);
  }

  /**
   * The claim fails closed while another request holds the lock.
   */
  public function testClaimFailsWhileAnotherRequestHoldsTheLock(): void {
    $this->held = ['manager_form_once:form-a'];
    $this->assertFalse($this->guard()->claim('form-a'));
    // The failed claim stored nothing and released no lock it did not get.
    $this->assertFalse($this->store->has('form-a'));
    $this->assertSame(['acquire manager_form_once:form-a'], $this->lockCalls);
    $this->held = [];
    $this->assertTrue($this->guard()->claim('form-a'));
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
   * An action runs once per posted build id; without one it does not run.
   */
  public function testRunOnce(): void {

    $action = function (): bool {
      $this->runs++;
      return TRUE;
    };
    $state = $this->stateWith(['form_build_id' => 'form-once']);
    $this->assertTrue($this->guard()->runOnce($state, $action));
    $this->assertNull($this->guard()->runOnce($state, $action));
    $this->assertSame(1, $this->runs);
    $this->assertTrue($this->store->has('form-once'), 'The claim is kept after a success.');

    // Without a usable form_build_id the action does not run (fail closed).
    foreach ([[], ['form_build_id' => ''], ['form_build_id' => ['form-a']], ['form_build_id' => 'junk']] as $input) {
      $this->assertNull($this->guard()->runOnce($this->stateWith($input), $action), json_encode($input));
    }
    $this->assertSame(1, $this->runs);
  }

  /**
   * A failed or throwing action releases the claim, so a retry runs.
   */
  public function testRunOnceReleasesTheClaimOnFailure(): void {

    $state = $this->stateWith(['form_build_id' => 'form-retry']);
    $this->assertFalse($this->guard()->runOnce($state, static fn (): bool => FALSE));
    $this->assertFalse($this->store->has('form-retry'));

    try {
      $this->guard()->runOnce($state, static function (): bool {
        throw new \RuntimeException('boom');
      });
      $this->fail('The exception must propagate.');
    }
    catch (\RuntimeException $exception) {
      $this->assertSame('boom', $exception->getMessage());
    }
    $this->assertFalse($this->store->has('form-retry'));

    $this->assertTrue($this->guard()->runOnce($state, static fn (): bool => TRUE));
    $this->assertNull($this->guard()->runOnce($state, static fn (): bool => TRUE));
  }

}
