<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectState;

/**
 * Tests which manager mails are offered for which project state.
 *
 * @group mailer
 */
class ManagerMailRegistryTest extends MailerKernelTestBase {

  /**
   * Draft offers only the draft reminder.
   */
  public function testDraft(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $registry = $this->container->get('mailer.manager_mail_registry');
    $this->assertSame(['reminder_draft'], array_map(static fn ($d) => $d->subtype, $registry->available($project)));
    $this->assertNull($registry->find($project, 'reminder_ongoing'));
  }

  /**
   * Open offers the variant that matches the applicants.
   */
  public function testOpenVariants(): void {
    $registry = $this->container->get('mailer.manager_mail_registry');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertSame(['reminder_open_noone'], array_map(static fn ($d) => $d->subtype, $registry->available($project)));
    $project->appendApplicant($this->createMailableCreative('Ann'));
    $project->save();
    $this->assertSame(['reminder_open'], array_map(static fn ($d) => $d->subtype, $registry->available($project)));
  }

  /**
   * Ongoing offers the ongoing reminder.
   */
  public function testOngoing(): void {
    $project = $this->createMailableProject(ProjectState::Ongoing);
    $registry = $this->container->get('mailer.manager_mail_registry');
    $this->assertSame(['reminder_ongoing'], array_map(static fn ($d) => $d->subtype, $registry->available($project)));
  }

  /**
   * Pending, completed and declined offer nothing.
   */
  public function testNoMailForOtherStates(): void {
    $registry = $this->container->get('mailer.manager_mail_registry');
    foreach ([ProjectState::Pending, ProjectState::Completed, ProjectState::Declined] as $state) {
      $this->assertSame([], $registry->available($this->createMailableProject($state)), $state->value);
    }
  }

  /**
   * Decline mails are not part of the normal manager mail pane.
   */
  public function testDeclineMailsAreNotAvailable(): void {
    $project = $this->createProject(ProjectState::Draft, TRUE);
    $project->save();
    $registry = $this->container->get('mailer.manager_mail_registry');
    $this->assertSame(['reminder_draft'], array_map(static fn ($d) => $d->subtype, $registry->available($project)));
  }

  /**
   * Decline mails are offered for submitted or prospect projects only.
   */
  public function testDeclineMailsByState(): void {
    $registry = $this->container->get('mailer.manager_mail_registry');
    $subtypes = static fn (array $definitions) => array_map(static fn ($d) => $d->subtype, $definitions);

    $prospectDraft = $this->createProject(ProjectState::Draft, TRUE);
    $prospectDraft->save();
    $declines = $registry->availableDeclines($prospectDraft);
    $this->assertCount(9, $declines);
    $this->assertSame('decline_custom', $declines[0]->subtype);

    $pending = $this->createProject(ProjectState::Pending);
    $pending->save();
    $declines = $registry->availableDeclines($pending);
    $this->assertCount(9, $declines);
    $this->assertSame('decline_custom', $declines[0]->subtype);
    $this->assertNotNull($registry->find($pending, 'decline_deadline'));

    $draft = $this->createProject(ProjectState::Draft);
    $draft->save();
    $this->assertSame([], $subtypes($registry->availableDeclines($draft)));
    foreach ([ProjectState::Open, ProjectState::Ongoing, ProjectState::Completed, ProjectState::Declined] as $state) {
      $project = $this->createProject($state);
      $project->save();
      $this->assertSame([], $registry->availableDeclines($project), $state->value);
    }
  }

  /**
   * The decline subtype maps to its reason and back.
   */
  public function testDeclineSubtypeRoundTrip(): void {
    foreach (ProjectDeclineReason::cases() as $reason) {
      $this->assertSame($reason, ManagerMailRegistry::declineReason(ManagerMailRegistry::declineSubtype($reason)));
    }
    $this->assertNull(ManagerMailRegistry::declineReason('reminder_draft'));
  }

  /**
   * Every decline policy has the shared subject and the contact variables.
   */
  public function testDeclinePolicySubjects(): void {
    $files = glob(DRUPAL_ROOT . '/../config/sync/mailer_policy.mailer_policy.mailer.manager.decline_*.yml');
    $this->assertCount(count(ProjectDeclineReason::cases()), $files);
    foreach ($files as $file) {
      $values = Yaml::decode((string) file_get_contents($file));
      $this->assertSame('Dein Projekt auf youvo.org', $values['configuration']['email_subject']['value'], $file);
      $body = $values['configuration']['email_body']['content']['value'];
      $this->assertStringContainsString('{{ contact }}', $body, $file);
      $this->assertStringContainsString('{{ manager }}', $body, $file);
      $this->assertStringNotContainsString('%Kontakt', $body, $file);
      $this->assertStringNotContainsString('%Manager', $body, $file);
    }
  }

}
