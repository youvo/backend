<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\mailer\Manager\LateTokens;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the late token replacement.
 */
#[CoversClass(LateTokens::class)]
#[Group('mailer')]
class LateTokensTest extends UnitTestCase {

  /**
   * Tokens are replaced case-insensitively; unknown ones are removed.
   */
  public function testReplace(): void {
    $tokens = new LateTokens();
    $link = 'https://hub.example.org/projects/abc';
    $this->assertSame("Hallo\n\n" . $link, $tokens->replace("Hallo\n\n[mail:project_link]", 'reminder_draft', ['project_link' => $link]));
    // Case-insensitive token, every occurrence.
    $this->assertSame($link . ' ' . $link, $tokens->replace('[MAIL:project_link] [mail:project_link]', 'reminder_draft', ['project_link' => $link]));
    // Unknown tokens are removed, not left in the mail.
    $this->assertSame('a  b', $tokens->replace('a [mail:unknown] b', 'reminder_draft', ['project_link' => $link]));
    // Mentions of the token name without brackets are untouched.
    $this->assertSame('project_link', $tokens->replace('project_link', 'reminder_draft', ['project_link' => $link]));
  }

  /**
   * The token text and the names allowed per subtype are exposed.
   */
  public function testTokenAndNames(): void {
    $tokens = new LateTokens();
    $this->assertSame('[mail:project_link]', LateTokens::token('project_link'));
    $this->assertSame(['project_link'], $tokens->names('reminder_open'));
    $this->assertSame(['faq_link', 'rules_link', 'new_project_link'], $tokens->names('decline_religious'));
    $this->assertSame(['new_draft_link'], $tokens->names('archive_external'));
    $this->assertSame([], $tokens->names('unknown'));
  }

  /**
   * Only the tokens of the subtype are resolved; the others are removed.
   */
  public function testReplaceOnlyAllowedTokens(): void {
    $tokens = new LateTokens();
    $values = array_combine(LateTokens::NAMES, array_map(static fn (string $name) => 'L-' . $name, LateTokens::NAMES));
    $text = '[mail:project_link]|[mail:faq_link]|[mail:rules_link]|[mail:new_project_link]|[mail:new_draft_link]';
    $this->assertSame('L-project_link||||', $tokens->replace($text, 'reminder_draft', $values));
    $this->assertSame('|L-faq_link|L-rules_link|L-new_project_link|', $tokens->replace($text, 'decline_imprecise', $values));
    $this->assertSame('||||L-new_draft_link', $tokens->replace($text, 'archive_unattractive', $values));
    $this->assertSame('||||', $tokens->replace($text, 'unknown', $values));
  }

  /**
   * Unknown tokens with digits or dashes are removed as well.
   */
  public function testUnknownTokenShapesAreRemoved(): void {
    $this->assertSame('a  b  c', (new LateTokens())->replace('a [mail:foo-bar] b [mail:x2] c', 'reminder_draft', []));
  }

}
