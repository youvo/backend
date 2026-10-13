<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\mailer\Mailer\YouvoMailerBase;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the redaction of secrets from recorded mails.
 *
 * @group mailer
 */
class SecretRedactionTest extends UnitTestCase {

  const LINK = "https://example.com/invite/1/2?a=b&c='d'#h-1";

  /**
   * The plain, the HTML-escaped and the URL-encoded forms are replaced.
   */
  public function testAllFormsAreReplaced(): void {
    $text = 'plain ' . self::LINK
      . ' attr <a href="' . htmlspecialchars(self::LINK) . '">x</a>'
      . ' entity ' . htmlspecialchars(self::LINK, ENT_QUOTES)
      . ' encoded ' . rawurlencode(self::LINK);
    $redacted = YouvoMailerBase::redactSecrets($text, [self::LINK]);
    $this->assertSame(4, substr_count($redacted, '[Link entfernt]'));
    $this->assertStringNotContainsString('example.com', $redacted);
    $this->assertStringNotContainsString('example%2Ecom', $redacted);
    $this->assertStringContainsString('<a href="[Link entfernt]">x</a>', $redacted);
  }

  /**
   * Empty secrets do not replace anything.
   */
  public function testEmptySecretIsIgnored(): void {
    $this->assertSame('abc', YouvoMailerBase::redactSecrets('abc', ['', '']));
    $this->assertSame('abc', YouvoMailerBase::redactSecrets('abc', []));
  }

  /**
   * Text without the secret stays as it is.
   */
  public function testOtherTextIsKept(): void {
    $this->assertSame('Hallo & <b>', YouvoMailerBase::redactSecrets('Hallo & <b>', [self::LINK]));
  }

}
