<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\projects\ProjectState;

/**
 * Tests the access check for manager mails.
 *
 * @group mailer
 */
class ManagerMailAccessTest extends MailerKernelTestBase {

  /**
   * Only the assigned manager with the permission may send.
   *
   * A subtype that is not offered in the current state is denied.
   */
  public function testAccess(): void {
    // User 1 bypasses all permission checks, so do not let the manager be it.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $access = $this->container->get('mailer.manager_mail_access');

    $this->assertTrue($access->access($this->accountWith($manager, ['send project mails']), $project, 'reminder_draft')->isAllowed());
    // Same manager without the permission.
    $this->assertFalse($access->access($this->accountWith($manager, []), $project, 'reminder_draft')->isAllowed());
    // Another user with the permission who is not the manager.
    $other = $this->createMailableCreative('Other');
    $this->assertFalse($access->access($this->accountWith($other, ['send project mails']), $project, 'reminder_draft')->isAllowed());
    // A user with the permission and `administer projects`.
    $this->assertTrue($access->access($this->accountWith($other, ['send project mails', 'administer projects']), $project, 'reminder_draft')->isAllowed());
    // Subtype not offered in this state.
    $this->assertFalse($access->access($this->accountWith($manager, ['send project mails']), $project, 'reminder_ongoing')->isAllowed());
    // Empty subtype means "may use the mail pane" (permission + manager only).
    $this->assertTrue($access->access($this->accountWith($manager, ['send project mails']), $project)->isAllowed());
  }

}
