<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;

/**
 * Tests that a skipped mail is neither sent nor recorded.
 *
 * The mailers throw a SkipMailException while the site is being installed. The
 * installer check is static, so the same mechanism is triggered here from a
 * callback in the init phase.
 *
 * @group mailer
 */
class SkipMailTest extends MailerKernelTestBase {

  /**
   * A skipped mail counts as handled, but nothing is sent or recorded.
   */
  public function testSkipExceptionPreventsSend(): void {
    $project = $this->createMailableProject();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    $mailer->addCallback(static function (): void {
      throw new SkipMailException('test');
    }, EmailInterface::PHASE_INIT, 1, 'skip');

    $this->assertTrue($mailer->publish($project));
    $this->assertCount(0, $this->capturedMails);
    $this->assertCount(0, $this->loadEntries($project->id()));
  }

  /**
   * Without the skip callback the same mail is sent and recorded.
   */
  public function testMailIsSentWithoutSkip(): void {
    $project = $this->createMailableProject();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);

    $this->assertTrue($mailer->publish($project));
    $this->assertCount(1, $this->capturedMails);
    $this->assertCount(1, $this->loadEntries($project->id()));
  }

}
