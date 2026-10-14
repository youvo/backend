<?php

namespace Drupal\Tests\logbook\Kernel;

/**
 * Tests the message build of logs.
 *
 * @group logbook
 */
class LogMessageBuildTest extends LogbookKernelTestBase {

  /**
   * A plain message is escaped plain text.
   */
  public function testPlainMessage(): void {
    $this->createPattern('project_apply', ['observable' => TRUE]);
    $log = $this->createLog('project_apply', ['message' => '<b>Hello</b>']);
    $this->assertSame(['#plain_text' => '<b>Hello</b>'], $log->buildMessage());
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
