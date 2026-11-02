<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the access check for manager mails with the roles of the site.
 */
#[Group('mailer')]
class ManagerMailAccessTest extends MailerExistingSiteTestBase {

  /**
   * Gets the access service.
   */
  protected function access(): ManagerMailAccess {
    return $this->container->get('mailer.manager_mail_access');
  }

  /**
   * Creates a creative with an additional role.
   */
  protected function createCreativeWithRole(string $role): Creative {
    $creative = $this->createCreative();
    $creative->addRole($role);
    $creative->save();
    return $creative;
  }

  /**
   * Only the assigned manager or a supervisor may send, each with permission.
   *
   * A subtype that is not offered in the current state is denied. The empty
   * subtype stands for the mail pane.
   */
  public function testAccess(): void {

    $project = $this->createMailableProject(ProjectState::Draft);
    $organization = $project->getOwner();
    $manager = $organization->getManager();
    $access = $this->access();

    // The assigned manager.
    $this->assertTrue($access->access($manager, $project, 'reminder_draft')->isAllowed());
    $this->assertTrue($access->access($manager, $project)->isAllowed());
    $this->assertFalse($access->access($manager, $project, 'reminder_ongoing')->isAllowed());

    // A supervisor manages every project.
    $supervisor = $this->createCreativeWithRole('supervisor');
    $this->assertTrue($access->access($supervisor, $project, 'reminder_draft')->isAllowed());
    $this->assertFalse($access->access($supervisor, $project, 'reminder_ongoing')->isAllowed());

    // A manager who is not assigned to the organization.
    $this->assertFalse($access->access($this->createCreativeWithRole('manager'), $project, 'reminder_draft')->isAllowed());

    // Administering projects without the send permission.
    $administrator = $this->createCreativeWithRole($this->createRole(['administer projects']));
    $this->assertFalse($access->access($administrator, $project, 'reminder_draft')->isAllowed());
    $this->assertFalse($access->access($administrator, $project)->isAllowed());

    // The organization itself, even with the send permission.
    $organization->addRole('manager')->save();
    $this->assertFalse($access->access($organization, $project, 'reminder_draft')->isAllowed());

    // Anonymous.
    $this->assertFalse($access->access(new AnonymousUserSession(), $project, 'reminder_draft')->isAllowed());

    // An assigned manager without the send permission.
    $unprivileged = $this->createCreative();
    $organization->set('field_manager', $unprivileged)->save();
    $this->assertTrue($organization->isManager($unprivileged));
    $this->assertFalse($access->access($unprivileged, $project, 'reminder_draft')->isAllowed());
  }

  /**
   * Decline subtypes are allowed for the manager of a pending project only.
   */
  public function testDeclineSubtypeAccess(): void {

    $access = $this->access();
    $pending = $this->createMailableProject(ProjectState::Pending);
    $this->assertTrue($access->access($pending->getOwner()->getManager(), $pending, 'decline_custom')->isAllowed());
    $this->assertFalse($access->access($this->createCreativeWithRole('manager'), $pending, 'decline_custom')->isAllowed());

    $open = $this->createMailableProject(ProjectState::Open);
    $this->assertFalse($access->access($open->getOwner()->getManager(), $open, 'decline_custom')->isAllowed());
  }

  /**
   * The result depends on the project, its owner and the user permissions.
   */
  public function testCacheability(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = $this->access()->access($project->getOwner()->getManager(), $project, 'reminder_draft');
    $this->assertContains($project->getOwner()->getCacheTags()[0], $result->getCacheTags());
    $this->assertContains($project->getCacheTags()[0], $result->getCacheTags());
    $this->assertContains('user', $result->getCacheContexts());
    $this->assertContains('user.permissions', $result->getCacheContexts());
  }

}
