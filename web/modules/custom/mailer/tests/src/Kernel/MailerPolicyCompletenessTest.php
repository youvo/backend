<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that every mailer subtype has a complete policy.
 *
 * A policy without subject or body makes the mailer skip the mail silently,
 * and an undefined variable renders as an empty string. This test guards both.
 *
 * @group mailer
 */
class MailerPolicyCompletenessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'filter',
    'system',
    'symfony_mailer',
    'mailer_policy',
    'mailer_transport',
    'correspondence',
    'child_entities',
    'content_translation',
    'language',
    'youvo',
    'mailer',
  ];

  /**
   * Every declared subtype has a policy with subject, body and known variables.
   */
  public function testPoliciesAreComplete(): void {
    $mailers = array_filter(
      $this->container->getParameter('symfony_mailer.mailers'),
      static fn (array $definition): bool => str_starts_with($definition['base_tag'], 'mailer.'),
    );
    $this->assertNotEmpty($mailers, 'At least one mailer of this module is registered.');

    $directory = DRUPAL_ROOT . '/../config/sync';
    foreach ($mailers as $definition) {
      $declared = array_keys($definition['variables']);
      $this->assertNotEmpty($definition['sub_defs'], $definition['base_tag'] . ' declares subtypes.');
      foreach (array_keys($definition['sub_defs']) as $subtype) {
        $id = $definition['base_tag'] . '.' . $subtype;
        $file = $directory . '/mailer_policy.mailer_policy.' . $id . '.yml';
        $this->assertFileExists($file, "Policy exists for $id.");
        $values = Yaml::decode((string) file_get_contents($file));
        $this->assertSame($id, $values['id']);
        $subject = $values['configuration']['email_subject']['value'] ?? '';
        $body = $values['configuration']['email_body']['content']['value'] ?? '';
        $this->assertNotSame('', trim($subject), "Subject of $id is not empty.");
        $this->assertNotSame('', trim($body), "Body of $id is not empty.");

        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)/', $subject . $body, $matches);
        $unknown = array_diff(array_unique($matches[1]), $declared);
        $this->assertSame([], array_values($unknown), "Policy $id uses undeclared variables.");
      }
    }
  }

}
