<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\mailer\Manager\LateTokens;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the late token replacement.
 *
 * @group mailer
 */
class LateTokensTest extends UnitTestCase {

  /**
   * Tokens are replaced case-insensitively; unknown ones are removed.
   */
  public function testReplace(): void {
    $tokens = new LateTokens();
    $link = 'https://hub.example.org/projects/abc';
    $this->assertSame("Hallo\n\n" . $link, $tokens->replace("Hallo\n\n[mail:project_link]", ['project_link' => $link]));
    // Case-insensitive token, every occurrence.
    $this->assertSame($link . ' ' . $link, $tokens->replace('[MAIL:project_link] [mail:project_link]', ['project_link' => $link]));
    // Unknown tokens are removed, not left in the mail.
    $this->assertSame('a  b', $tokens->replace('a [mail:unknown] b', ['project_link' => $link]));
    // Mentions of the token name without brackets are untouched.
    $this->assertSame('project_link', $tokens->replace('project_link', ['project_link' => $link]));
  }

  /**
   * The token text and names are exposed.
   */
  public function testTokenAndNames(): void {
    $this->assertSame('[mail:project_link]', LateTokens::token('project_link'));
    $this->assertSame(['project_link'], (new LateTokens())->names());
  }

  /**
   * Unknown tokens with digits or dashes are removed as well.
   */
  public function testUnknownTokenShapesAreRemoved(): void {
    $this->assertSame('a  b  c', (new LateTokens())->replace('a [mail:foo-bar] b [mail:x2] c', []));
  }

}
