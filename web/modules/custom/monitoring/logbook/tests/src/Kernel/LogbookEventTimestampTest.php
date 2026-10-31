<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\creatives\Event\CreativeRegisterEvent;
use Drupal\feedback\Event\FeedbackCompleteEvent;
use Drupal\feedback\FeedbackInterface;
use Drupal\logbook\Entity\Log;

/**
 * Tests that the register and feedback logs take the time of their event.
 *
 * The dummy content logs registrations and feedback in the past.
 *
 * @group logbook
 */
class LogbookEventTimestampTest extends LogbookKernelTestBase {

  /**
   * A creative registered at a given time is logged at that time.
   */
  public function testCreativeRegisterLog(): void {
    $this->createPattern('creative_register');
    $creative = $this->createCreative();
    $this->eventDispatcher->dispatch(new CreativeRegisterEvent($creative, 'https://example.com', 1000));
    $this->eventDispatcher->dispatch(new CreativeRegisterEvent($creative, 'https://example.com'));

    $logs = array_values(Log::loadMultiple());
    $this->assertCount(2, $logs);
    $this->assertSame(1000, (int) $logs[0]->get('created')->value);
    // Without a time, the log is written now.
    $this->assertGreaterThan(1000, (int) $logs[1]->get('created')->value);
  }

  /**
   * A feedback completed at a given time is logged at that time.
   */
  public function testFeedbackCompleteLog(): void {
    $this->createPattern('feedback_complete');
    $project = $this->createLogbookProject();
    $feedback = $this->createMock(FeedbackInterface::class);
    $feedback->method('getProject')->willReturn($project);
    $feedback->method('id')->willReturn(7);
    $this->eventDispatcher->dispatch(new FeedbackCompleteEvent($feedback, 2000));

    $logs = Log::loadMultiple();
    $this->assertCount(1, $logs);
    $log = reset($logs);
    $this->assertSame(2000, (int) $log->get('created')->value);
    $this->assertSame(['feedback_id' => 7], $log->getMisc());
  }

}
