<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\organizations\Entity\Organization;
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

  /**
   * Each missing condition denies on its own.
   */
  public function testEachConditionIsRequired(): void {
    // User 1 bypasses all permission checks.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Draft);
    $organization = $project->getOwner();
    $access = $this->container->get('mailer.manager_mail_access');

    // Manager of another organization with the permission.
    $otherManager = $this->createMailableCreative('OtherManager');
    $otherOrganization = Organization::create([
      'name' => 'Other org',
      'field_manager' => $otherManager,
    ]);
    $otherOrganization->save();
    $this->assertTrue($otherOrganization->isManager($otherManager));
    $this->assertFalse($access->access($this->accountWith($otherManager, ['send project mails']), $project, 'reminder_draft')->isAllowed());

    // Administer projects without the send permission.
    $admin = $this->createMailableCreative('Admin');
    $this->assertFalse($access->access($this->accountWith($admin, ['administer projects']), $project, 'reminder_draft')->isAllowed());
    $this->assertFalse($access->access($admin, $project)->isAllowed());

    // The organization itself is not the assigned manager.
    $this->assertFalse($organization->isManager($organization));
    $this->assertFalse($access->access($this->accountWith($organization, ['send project mails']), $project, 'reminder_draft')->isAllowed());

    // Anonymous.
    $this->assertFalse($access->access(new AnonymousUserSession(), $project, 'reminder_draft')->isAllowed());

    // Admin with the permission, subtype not offered in this state.
    $this->accountWith($admin, ['administer projects', 'send project mails']);
    $this->assertTrue($access->access($admin, $project, 'reminder_draft')->isAllowed());
    $this->assertFalse($access->access($admin, $project, 'reminder_ongoing')->isAllowed());
  }

  /**
   * The result depends on the project, its owner and the user permissions.
   */
  public function testCacheability(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['send project mails']);
    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = $this->container->get('mailer.manager_mail_access')->access($manager, $project, 'reminder_draft');
    $this->assertContains($project->getOwner()->getCacheTags()[0], $result->getCacheTags());
    $this->assertContains($project->getCacheTags()[0], $result->getCacheTags());
    $this->assertContains('user', $result->getCacheContexts());
    $this->assertContains('user.permissions', $result->getCacheContexts());
  }

}
