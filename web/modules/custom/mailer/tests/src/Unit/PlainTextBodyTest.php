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

  /**
   * Scripts and styles are dropped, list items become dashes.
   */
  public function testFromHtmlDropsScriptsAndStyles(): void {
    $html = '<style>p { color: red; }</style><p>Hallo</p><script>alert(1)</script><ul><li>Eins</li><li>Zwei</li></ul>';
    $this->assertSame("Hallo\n\n- Eins\n- Zwei", (new PlainTextBody())->fromHtml($html));
  }

  /**
   * Provides inputs and the expected HTML.
   */
  public static function linkProvider(): array {
    $a = static fn (string $u): string => '<a href="' . $u . '">' . $u . '</a>';
    return [
      'double quotes' => ['"https://x.org"', '<p>&quot;' . $a('https://x.org') . '&quot;</p>'],
      'angle brackets' => ['<https://x.org>', '<p>&lt;' . $a('https://x.org') . '&gt;</p>'],
      'parentheses' => ['(siehe https://x.org/a)', '<p>(siehe ' . $a('https://x.org/a') . ')</p>'],
      'trailing dot' => ['https://x.org/a.', '<p>' . $a('https://x.org/a') . '.</p>'],
      'trailing comma' => ['https://x.org/a,', '<p>' . $a('https://x.org/a') . ',</p>'],
      'query' => ['https://x.org/?a=1&b=2', '<p>' . $a('https://x.org/?a=1&amp;b=2') . '</p>'],
      'injection' => ['https://x"onmouseover="x', '<p>' . $a('https://x') . '&quot;onmouseover=&quot;x</p>'],
      'entity text' => ['a &lt; b', '<p>a &amp;lt; b</p>'],
      'leading newline' => ["\nHallo", '<p>Hallo</p>'],
      'blank line with spaces' => ["a\n \nb", '<p>a</p><p>b</p>'],
      'empty' => ['', ''],
      'whitespace only' => ["  \n ", ''],
    ];
  }

  /**
   * Links are delimited before escaping and nothing else is raw HTML.
   *
   * @dataProvider linkProvider
   */
  public function testToHtmlLinks(string $text, string $expected): void {
    $html = (new PlainTextBody())->toHtml($text);
    $this->assertSame($expected, $html);
    $bare = preg_replace(['#</?p>#', '#<br>#', '#<a href="[^"<>]*">#', '#</a>#'], '', $html);
    $this->assertDoesNotMatchRegularExpression('/[<>"]/', $bare);
  }

}
