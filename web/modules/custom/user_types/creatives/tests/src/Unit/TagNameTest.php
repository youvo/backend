<?php

declare(strict_types=1);

namespace Drupal\Tests\creatives\Unit;

use Drupal\creatives\TagName;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the format of the tag names.
 */
#[Group('creatives')]
#[CoversClass(TagName::class)]
class TagNameTest extends UnitTestCase {

  /**
   * Tests that names become lowercase words joined by hyphens.
   */
  public function testFormat(): void {
    $this->assertSame('online-marketing', TagName::format('  Online   Marketing '));
    $this->assertSame('erklärfilm', TagName::format('ERKLÄRFILM'));
    $this->assertSame('straße', TagName::format('Straße'));
    $this->assertSame('nodejs', TagName::format('Node.js'));
    $this->assertSame('after-effects', TagName::format('- After -- Effects -'));
    $this->assertSame('3d', TagName::format('3D!'));
    $this->assertSame('', TagName::format('#+*'));
  }

}
