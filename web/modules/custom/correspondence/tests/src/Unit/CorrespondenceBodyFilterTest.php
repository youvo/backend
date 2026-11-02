<?php

namespace Drupal\Tests\correspondence\Unit;

use Drupal\correspondence\CorrespondenceBodyFilter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the filter of stored mail bodies.
 */
#[CoversClass(CorrespondenceBodyFilter::class)]
#[Group('correspondence')]
class CorrespondenceBodyFilterTest extends UnitTestCase {

  /**
   * Hostile bodies do not survive.
   */
  #[DataProvider('hostileBodies')]
  public function testHostileBody(string $body): void {
    $html = CorrespondenceBodyFilter::filter($body);
    foreach (['<script', '<style', '<iframe', '<img', 'onerror', 'onclick', 'onmouseover', 'javascript:'] as $needle) {
      $this->assertStringNotContainsStringIgnoringCase($needle, $html);
    }
  }

  /**
   * Provides hostile bodies.
   */
  public static function hostileBodies(): array {
    return [
      'script' => ['<script>alert(1)</script>'],
      'event handler' => ['<p onclick="alert(1)">x</p>'],
      'image' => ['<img src=x onerror=alert(1)>'],
      'javascript link' => ['<a href="javascript:alert(1)">x</a>'],
      'mixed case javascript link' => ['<a href="JaVaScRiPt:alert(1)">x</a>'],
      'style' => ['<style>body{display:none}</style>'],
      'iframe' => ['<iframe src="https://evil.example"></iframe>'],
      'link with event handler' => ['<a href="https://example.com" onmouseover="alert(1)">x</a>'],
      'mixed' => ['<p onclick="x()">Hi</p><script>alert(1)</script><a href="javascript:alert(1)">b</a>'],
    ];
  }

  /**
   * Legitimate markup survives; links keep only href plus rel.
   */
  public function testAllowedMarkupAndAttributes(): void {
    $markup = '<p>Hallo<br>Welt <strong>fett</strong> <a href="https://example.com/x" rel="noopener noreferrer">Link</a></p>';
    $this->assertSame($markup, CorrespondenceBodyFilter::filter($markup));

    $html = CorrespondenceBodyFilter::filter('<a href="https://x" class="js-timeline-filter" id="i" target="_blank" style="color:red" data-x="1" onclick="x()">l</a><p class="c" id="d" style="x">p</p><strong class="c">s</strong><ul class="c"><li id="e">li</li></ul>');
    $this->assertStringContainsString('<a href="https://x" rel="noopener noreferrer">l</a>', $html);
    foreach (['js-timeline-filter', 'target', 'style', 'data-x', 'onclick', 'class="c"', 'id="d"', 'id="e"', 'id="i"'] as $needle) {
      $this->assertStringNotContainsString($needle, $html);
    }
    $this->assertStringContainsString('<p>p</p>', $html);
    $this->assertStringContainsString('<strong>s</strong>', $html);
    $this->assertStringContainsString('<li>li</li>', $html);
  }

  /**
   * An unclosed element is closed, so it cannot wrap what follows.
   */
  public function testUnclosedMarkupIsBalanced(): void {
    $html = CorrespondenceBodyFilter::filter('<p>one <a href="https://x.example">y');
    $this->assertSame(1, substr_count($html, '<a '));
    $this->assertSame(1, substr_count($html, '</a>'));
    $this->assertSame(substr_count($html, '<p>'), substr_count($html, '</p>'));
    $this->assertStringEndsWith('</p>', $html);
  }

}
