<?php

namespace Drupal\Tests\logbook\Kernel;

/**
 * Tests the message build of logs.
 *
 * @group logbook
 */
class LogMessageBuildTest extends LogbookKernelTestBase {

  /**
   * A plain message is escaped and keeps its line breaks.
   */
  public function testPlainMessage(): void {
    $this->createPattern('project_apply', ['observable' => TRUE]);
    $log = $this->createLog('project_apply', ['message' => "<b>Hello</b>\nWorld"]);
    $build = $log->buildMessage();
    $this->assertSame("&lt;b&gt;Hello&lt;/b&gt;<br />\nWorld", (string) $this->container->get('renderer')->renderInIsolation($build));
  }

  /**
   * No message gives an empty build.
   */
  public function testEmptyMessage(): void {
    $this->createPattern('project_apply', ['observable' => TRUE]);
    $log = $this->createLog('project_apply');
    $this->assertSame([], $log->buildMessage());
  }

  /**
   * A feedback log without a feedback id shows no link and does not fail.
   */
  public function testFeedbackWithoutId(): void {
    $this->createPattern('feedback_create');
    $log = $this->createLog('feedback_create');
    $this->assertSame([], $log->buildMessage());
  }

}
