<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\logbook\Entity\LogPattern;
use Drupal\logbook\LogPatternInterface;

/**
 * Tests the tone setting of log patterns.
 *
 * @group logbook
 */
class LogPatternToneTest extends LogbookKernelTestBase {

  /**
   * The tone is stored and read back.
   */
  public function testToneIsStored(): void {
    $this->createPattern('project_complete', ['tone' => 'celebrate']);
    $pattern = LogPattern::load('project_complete');
    $this->assertSame('celebrate', $pattern->getTone());
    $this->assertContains('celebrate', LogPatternInterface::TONES);
  }

  /**
   * A pattern saved before the property existed has an empty tone.
   */
  public function testMissingToneIsEmpty(): void {
    $pattern = LogPattern::create([
      'id' => 'legacy',
      'label' => 'Legacy',
      'tokens' => [],
      'status' => TRUE,
      'detectable' => TRUE,
      'observable' => TRUE,
      'public' => FALSE,
      'promote' => FALSE,
      'hidden' => FALSE,
      'color' => '',
    ]);
    $pattern->save();
    $this->assertSame('', LogPattern::load('legacy')->getTone());
  }

}
