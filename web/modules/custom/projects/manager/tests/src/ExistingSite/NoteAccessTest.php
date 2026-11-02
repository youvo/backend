<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\correspondence\Entity\Correspondence;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the note access that the logbook pane and the note forms rely on.
 */
#[Group('manager')]
class NoteAccessTest extends ManagerExistingSiteTestBase {

  /**
   * Gets the access service.
   */
  protected function access(): ManagerMailAccess {
    return $this->container->get('mailer.manager_mail_access');
  }

  /**
   * Only the project's manager with the permission, or a supervisor, may.
   */
  public function testAccessOfManagers(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $note = $this->createNote($project);
    $access = $this->access();

    $this->assertTrue($access->noteAccess($manager, $project)->isAllowed());
    $this->assertTrue($access->noteEntryAccess($manager, $note)->isAllowed());

    // A manager without an organization and the manager of another
    // organization are denied.
    $other = $this->createManager();
    $other_project = $this->createProject(ProjectState::Open);
    $other_manager = $this->managerOf($other_project);
    foreach ([$other, $other_manager] as $account) {
      $this->assertFalse($access->noteAccess($account, $project)->isAllowed());
      $this->assertFalse($access->noteEntryAccess($account, $note)->isAllowed());
    }
    $this->assertTrue($access->noteAccess($other_manager, $other_project)->isAllowed());

    // A supervisor who is not the manager is allowed.
    $supervisor = $this->createSupervisor();
    $this->assertTrue($access->noteAccess($supervisor, $project)->isAllowed());
    $this->assertTrue($access->noteEntryAccess($supervisor, $note)->isAllowed());
  }

  /**
   * The assigned manager without the permission is denied.
   */
  public function testManagerWithoutPermissionIsDenied(): void {
    $creative = $this->createCreative();
    $project = $this->createProject(ProjectState::Open, $this->createOrganization($creative));
    $note = $this->createNote($project);
    $this->assertTrue($project->getOwner()->isManager($creative));
    $this->assertFalse($this->access()->noteAccess($creative, $project)->isAllowed());
    $this->assertFalse($this->access()->noteEntryAccess($creative, $note)->isAllowed());
  }

  /**
   * A mail entry is not a note, a dangling parent is denied.
   */
  public function testEntryAccessRejectsMailsAndDanglingParents(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $mail = $this->createMailEntry($project);
    $this->assertFalse($this->access()->noteEntryAccess($manager, $mail)->isAllowed());
    $gone = $this->createProject(ProjectState::Open, $project->getOwner());
    $gone_id = $gone->id();
    $this->deleteCreated($gone);
    $dangling = Correspondence::create(['type' => 'note', 'project' => $gone_id, 'subject' => 'N']);
    $this->assertFalse($this->access()->noteEntryAccess($manager, $dangling)->isAllowed());
  }

}
