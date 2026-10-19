<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\mailer\PreviewText;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the inbox preview text derived from a mail body.
 *
 * @group mailer
 */
class PreviewTextTest extends UnitTestCase {

  /**
   * The greeting is skipped and the following paragraphs are joined.
   */
  public function testSkipsGreeting(): void {
    $html = '<p>Hallo Evi Vogt,</p><p>Dein Projekt <a href="https://example.org">Test</a> wurde veröffentlicht.</p><p>Liebe Grüße<br>Dein youvo-Team</p>';
    $this->assertSame('Dein Projekt Test wurde veröffentlicht. Liebe Grüße Dein youvo-Team', PreviewText::fromHtml($html));
    // The sentence after the greeting is capitalized.
    $this->assertSame('Über Dein Projekt', PreviewText::fromHtml('<p>Hallo Evi,</p><p>über Dein Projekt</p>'));
  }

  /**
   * A first paragraph that is not a greeting is kept.
   */
  public function testKeepsFirstParagraph(): void {
    $this->assertSame('Hallo, ich habe Interesse. Bitte schaut Euch das an', PreviewText::fromHtml('<p>Hallo, ich habe Interesse.</p><p>========</p><p>Bitte schaut Euch das an</p>'));
    // A long first paragraph ending with a comma is not a greeting.
    $long = str_repeat('Wort ', 15) . 'Ende,';
    $this->assertSame($long . ' Mehr', PreviewText::fromHtml("<p>$long</p><p>Mehr</p>"));
    // A greeting alone is the whole text.
    $this->assertSame('Hallo zusammen,', PreviewText::fromHtml('<p>Hallo zusammen,</p>'));
  }

  /**
   * The text is decoded, collapsed and cut at a word boundary.
   */
  public function testTruncatesPlainText(): void {
    $this->assertSame('Tom & Jerry', PreviewText::fromHtml("<p>Hallo,</p><p>Tom &amp;\n  Jerry</p>"));
    $preview = PreviewText::fromHtml('<p>' . str_repeat('Langer Satz ', 20) . '</p>', 40);
    $this->assertLessThanOrEqual(40, mb_strlen($preview));
    $this->assertStringEndsWith('…', $preview);
    $this->assertStringStartsWith('Langer Satz', $preview);
    $this->assertSame('', PreviewText::fromHtml('<p> </p>'));
  }

}
