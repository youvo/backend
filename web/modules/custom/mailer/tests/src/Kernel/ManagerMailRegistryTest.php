<?php

namespace Drupal\Tests\mailer\Kernel;

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
   * Pending, completed and archived offer nothing.
   */
  public function testNoMailForOtherStates(): void {
    $registry = $this->container->get('mailer.manager_mail_registry');
    foreach ([ProjectState::Pending, ProjectState::Completed, ProjectState::Archived] as $state) {
      $this->assertSame([], $registry->available($this->createMailableProject($state)), $state->value);
    }
  }

}
