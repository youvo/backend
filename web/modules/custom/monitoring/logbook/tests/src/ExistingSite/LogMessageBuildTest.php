<?php

namespace Drupal\Tests\logbook\ExistingSite;

use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the message build of logs.
 */
#[Group('logbook')]
class LogMessageBuildTest extends ProjectResourceTestBase {

  /**
   * Messages are escaped and keep their line breaks; without one, no build.
   */
  public function testBuildMessage(): void {

    $build = $this->createLog('project_apply', "<b>Hello</b>\nWorld")->buildMessage();
    $this->assertSame("&lt;b&gt;Hello&lt;/b&gt;<br />\nWorld", (string) $this->container->get('renderer')->renderInIsolation($build));

    $this->assertSame([], $this->createLog('project_apply')->buildMessage());

    // A feedback log without a feedback id shows no link and does not fail.
    $this->assertSame([], $this->createLog('feedback_create', 'Ignored')->buildMessage());
  }

  /**
   * Creates a log of a pattern with a message.
   */
  protected function createLog(string $type, string $message = ''): LogInterface {
    $log = Log::create(['type' => $type, 'author' => $this->createCreative()->id(), 'message' => $message]);
    $log->save();
    $this->markEntityForCleanup($log);
    return $log;
  }

}
