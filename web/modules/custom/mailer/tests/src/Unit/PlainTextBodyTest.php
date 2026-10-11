<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\mailer\Manager\PlainTextBody;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the conversion between HTML and plain text bodies.
 *
 * @group mailer
 */
class PlainTextBodyTest extends UnitTestCase {

  /**
   * Rendered HTML becomes plain text.
   */
  public function testFromHtml(): void {
    $text = (new PlainTextBody())->fromHtml('<p>Hallo Olga,</p><p>Zeile 1<br>Zeile 2 &amp; mehr</p><p>[mail:project_link]</p>');
    $this->assertSame("Hallo Olga,\n\nZeile 1\nZeile 2 & mehr\n\n[mail:project_link]", $text);
  }

  /**
   * Plain text is escaped and URLs are linked.
   */
  public function testToHtmlEscapesAndLinks(): void {
    $html = (new PlainTextBody())->toHtml("Hallo <b>Welt</b> & Co\nzweite Zeile\n\nSiehe https://hub.example.org/projects/abc?x=1&y=2.");
    $this->assertSame('<p>Hallo &lt;b&gt;Welt&lt;/b&gt; &amp; Co<br>zweite Zeile</p><p>Siehe <a href="https://hub.example.org/projects/abc?x=1&amp;y=2">https://hub.example.org/projects/abc?x=1&amp;y=2</a>.</p>', $html);
  }

  /**
   * Raw HTML and Twig-like text never survive unescaped.
   */
  public function testToHtmlNeverEmitsRawHtml(): void {
    $html = (new PlainTextBody())->toHtml('<script>alert(1)</script> <a href="javascript:alert(1)">x</a> {{ 1+1 }}');
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringNotContainsString('<a href="javascript', $html);
    $this->assertStringContainsString('{{ 1+1 }}', $html);
  }

}
