<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\Component\Serialization\Yaml;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectDeclineReason;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests that every mailer subtype has a complete policy in the exported config.
 *
 * A policy without subject or body makes the mailer skip the mail silently,
 * and an undefined variable renders as an empty string. This test guards both.
 */
#[Group('mailer')]
class MailerPolicyCompletenessTest extends ExistingSiteBase {

  /**
   * Reads an exported mailer policy.
   *
   * @return array{subject: string, body: string, id: string}
   *   The subject, body and ID of the policy.
   */
  protected function readPolicy(string $file): array {
    $values = Yaml::decode((string) file_get_contents($file));
    return [
      'id' => $values['id'] ?? '',
      'subject' => $values['configuration']['email_subject']['value'] ?? '',
      'body' => $values['configuration']['email_body']['content']['value'] ?? '',
    ];
  }

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
        $policy = $this->readPolicy($file);
        $this->assertSame($id, $policy['id']);
        $this->assertNotSame('', trim($policy['subject']), "Subject of $id is not empty.");
        $this->assertNotSame('', trim($policy['body']), "Body of $id is not empty.");

        preg_match_all('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)/', $policy['subject'] . $policy['body'], $matches);
        $unknown = array_diff(array_unique($matches[1]), $declared);
        $this->assertSame([], array_values($unknown), "Policy $id uses undeclared variables.");
      }
    }
  }

  /**
   * Every decline policy has the shared subject and the contact variables.
   */
  public function testDeclinePolicies(): void {
    $files = glob(DRUPAL_ROOT . '/../config/sync/mailer_policy.mailer_policy.mailer.manager.decline_*.yml') ?: [];
    $this->assertCount(count(ProjectDeclineReason::cases()), $files);
    foreach ($files as $file) {
      $policy = $this->readPolicy($file);
      $this->assertSame('Dein Projekt auf youvo.org', $policy['subject'], $file);
      $this->assertStringContainsString('{{ contact }}', $policy['body'], $file);
      $this->assertStringContainsString('{{ manager }}', $policy['body'], $file);
      $this->assertStringNotContainsString('%Kontakt', $policy['body'], $file);
      $this->assertStringNotContainsString('%Manager', $policy['body'], $file);
    }
  }

  /**
   * Every archive policy has the shared subject and the contact variables.
   */
  public function testArchivePolicies(): void {
    $files = glob(DRUPAL_ROOT . '/../config/sync/mailer_policy.mailer_policy.mailer.manager.archive_*.yml') ?: [];
    $this->assertCount(count(ProjectArchiveReason::cases()), $files);
    foreach ($files as $file) {
      $policy = $this->readPolicy($file);
      $this->assertSame('Euer Projekt „{{ project_title }}“ auf youvo.org', $policy['subject'], $file);
      $this->assertStringContainsString('{{ contact }}', $policy['body'], $file);
      $this->assertStringContainsString('{{ manager }}', $policy['body'], $file);
    }
  }

}
