<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the changes card on the project page.
 */
#[Group('projects')]
class ProjectChangesCardTest extends ProjectResourceTestBase {

  /**
   * Creates a pending project with title changes by its organization.
   */
  protected function createChangedProject(int $changes): ProjectInterface {
    $project = $this->createProject(ProjectState::Pending);
    $this->setCurrentUser($project->getOwner());
    for ($i = 1; $i <= $changes; $i++) {
      $project->setTitle('Titel ' . $i)->save();
    }
    return $project;
  }

  /**
   * Tests that staff see the collapsed card with the entries.
   */
  public function testStaffSeeCard(): void {

    $project = $this->createChangedProject(1);
    $revision_id = (int) $project->getRevisionId();

    $this->drupalLogin($this->createSupervisor());
    $this->drupalGet($project->toUrl());

    $session = $this->assertSession();
    $card = $session->elementExists('css', 'details.project-changes');
    $this->assertFalse($card->hasAttribute('open'));
    $session->elementTextContains('css', 'details.project-changes > summary', '2 changes');
    $session->elementTextContains('css', '#revision-' . $revision_id, 'Project Title');
    $session->elementExists('css', '#revision-' . $revision_id . ' a[href$="/projects/' . $project->id() . '/revisions/' . $revision_id . '/view"]');

    // Entries read like the logbook: a type pill and a sentence naming the
    // author with a link.
    $session->elementTextEquals('css', '#revision-' . $revision_id . ' .project-changes-type', 'Change');
    $session->elementExists('css', '#revision-' . $revision_id . ' .project-changes-text a[href$="/user/' . $project->getOwner()->id() . '"]');
  }

  /**
   * Tests that the organization doesn't see the card, after staff did.
   */
  public function testOrganizationDoesNotSeeCard(): void {

    $project = $this->createChangedProject(1);

    $this->drupalLogin($this->createSupervisor());
    $this->drupalGet($project->toUrl());
    $this->assertSession()->elementExists('css', 'details.project-changes');

    $this->drupalLogin($project->getOwner());
    $this->drupalGet($project->toUrl());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementNotExists('css', '.project-changes');
  }

  /**
   * Tests that entries beyond the two newest are nested.
   */
  public function testOlderEntriesNested(): void {

    $project = $this->createChangedProject(3);

    $this->drupalLogin($this->createSupervisor());
    $this->drupalGet($project->toUrl());

    $session = $this->assertSession();
    $session->elementsCount('css', 'details.project-changes > .project-changes-entries > .project-changes-entry', 2);
    $session->elementsCount('css', 'details.project-changes-older .project-changes-entry', 2);
    $session->elementTextContains('css', 'details.project-changes-older > summary', 'Show 2 older changes');
  }

}
