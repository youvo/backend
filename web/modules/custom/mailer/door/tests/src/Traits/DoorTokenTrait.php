<?php

declare(strict_types=1);

namespace Drupal\Tests\mailer_door\Traits;

use Drupal\mailer_door\DoorLinks;
use Drupal\mailer_door\DoorTarget;
use Drupal\mailer_door\DoorToken;

/**
 * Reads and tampers with the door tokens of mails and URLs.
 *
 * The token is opaque, so tests parse it to check what a door carries.
 */
trait DoorTokenTrait {

  /**
   * Gets the door tokens linked in a text, in order.
   *
   * @return string[]
   *   The tokens.
   */
  protected function doorTokens(string $text): array {
    preg_match_all('#/go/([A-Za-z0-9_-]+)#', $text, $matches);
    return $matches[1];
  }

  /**
   * Parses a door token and fails the test if it is malformed.
   */
  protected function parseDoor(string $token): DoorToken {
    $door = $this->container->get(DoorLinks::class)->parse($token);
    $this->assertNotNull($door, 'The door token parses: ' . $token);
    return $door;
  }

  /**
   * Asserts that a text links a door with the given facts.
   *
   * Every door in the text must carry the facts. A NULL correspondence is
   * not checked.
   */
  protected function assertDoor(string $text, int|string $uid, ?string $correspondence, DoorTarget $target, string $subject = '', string $message = ''): void {
    $tokens = $this->doorTokens($text);
    $this->assertNotEmpty($tokens, 'The text links a door. ' . $message);
    foreach ($tokens as $token) {
      $door = $this->parseDoor($token);
      $this->assertSame((int) $uid, $door->uid, 'The door of the recipient. ' . $message);
      if ($correspondence !== NULL) {
        $this->assertSame($correspondence, $door->correspondence, 'The door of the entry. ' . $message);
      }
      $this->assertSame($target, $door->target, 'The door target. ' . $message);
      $this->assertSame($subject, $door->subject, 'The door subject. ' . $message);
    }
  }

  /**
   * Overwrites bytes of a door token and keeps the rest, including the MAC.
   *
   * The payload is: version (offset 0), uid (1), correspondence (5), target
   * (21), timestamp (22) and the optional subject (26), then the MAC.
   */
  protected function tamperDoor(string $token, int $offset, string $bytes): string {
    $decoded = base64_decode(strtr($token, '-_', '+/'), TRUE);
    $this->assertIsString($decoded);
    $tampered = substr_replace($decoded, $bytes, $offset, strlen($bytes));
    $this->assertSame(strlen($decoded), strlen($tampered));
    return rtrim(strtr(base64_encode($tampered), '+/', '-_'), '=');
  }

}
