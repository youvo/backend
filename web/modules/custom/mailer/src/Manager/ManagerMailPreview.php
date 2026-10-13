<?php

namespace Drupal\mailer\Manager;

/**
 * A rendered preview of a manager mail.
 */
final class ManagerMailPreview {

  /**
   * Constructs a preview.
   *
   * @param string $subject
   *   The rendered subject (plain text).
   * @param string $body
   *   The rendered inner body as plain text, with late tokens left in place.
   * @param string[] $recipients
   *   The recipient addresses.
   */
  public function __construct(
    public readonly string $subject,
    public readonly string $body,
    public readonly array $recipients,
  ) {}

}
