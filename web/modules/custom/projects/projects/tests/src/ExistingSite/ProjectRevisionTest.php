<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\file\Entity\File;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that content changes of projects create revisions.
 */
#[Group('projects')]
class ProjectRevisionTest extends ProjectResourceTestBase {

  /**
   * Gets the revision IDs of a project, oldest first.
   *
   * @return int[]
   *   The revision IDs.
   */
  protected function revisionIds(ProjectInterface $project): array {
    $ids = $this->container->get('entity_type.manager')->getStorage('project')->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('id', $project->id())
      ->sort('revision_id')
      ->execute();
    return array_map('intval', array_keys($ids));
  }

  /**
   * Tests that a new project has one revision, despite its second save.
   */
  public function testNewProjectHasOneRevision(): void {
    $project = $this->createProject(ProjectState::Pending);
    $this->assertCount(1, $this->revisionIds($project));
  }

  /**
   * Tests that a content change creates a revision, also in draft.
   */
  public function testContentChangeCreatesRevision(): void {

    $organization = $this->createOrganization();
    $this->setCurrentUser($organization);
    $project = $this->createProject();
    [$first_revision_id] = $this->revisionIds($project);

    $project->setTitle('Changed')->save();

    $revision_ids = $this->revisionIds($project);
    $this->assertCount(2, $revision_ids);
    $this->assertSame(['title'], $project->getChangedContentFields());
    $this->assertSame($first_revision_id, $project->getPreviousRevisionId());

    /** @var \Drupal\projects\ProjectInterface $revision */
    $revision = $this->container->get('entity_type.manager')->getStorage('project')->loadRevision($revision_ids[1]);
    $this->assertSame('Changed', $revision->getTitle());
    $this->assertSame('title', $revision->getRevisionLogMessage());
    $this->assertSame((int) $organization->id(), (int) $revision->getRevisionUserId());
  }

  /**
   * Tests that workflow saves update the current revision.
   */
  public function testWorkflowSaveKeepsRevision(): void {

    $project = $this->createProject(ProjectState::Pending);

    $project->appendApplicant($this->createCreative())->save();
    $this->assertSame([], $project->getChangedContentFields());
    $project->setPromoted(TRUE)->save();
    $project->lifecycle()->publish();
    $project->save();

    $this->assertCount(1, $this->revisionIds($project));
    $this->assertSame([], $project->getChangedContentFields());
  }

  /**
   * Tests that syncing saves create no revision.
   */
  public function testSyncingSaveKeepsRevision(): void {

    $project = $this->createProject(ProjectState::Pending);

    $project->setSyncing(TRUE);
    $project->setTitle('Synced')->save();

    $this->assertCount(1, $this->revisionIds($project));
    $this->assertSame([], $project->getChangedContentFields());
  }

  /**
   * Tests that a content change in a translation creates a revision.
   */
  public function testTranslationChangeCreatesRevision(): void {

    $project = $this->createProject(ProjectState::Pending);
    $project->addTranslation('en', ['title' => 'English'])->save();
    $revision_count = count($this->revisionIds($project));

    $project = $this->reloadProject($project);
    $project->getTranslation('en')->setTitle('English changed')->save();

    $this->assertCount($revision_count + 1, $this->revisionIds($project));
    $this->assertSame(['title'], $project->getChangedContentFields());
  }

  /**
   * Creates an image file.
   */
  protected function createImage(): File {
    $uri = $this->container->get('file_system')->copy('core/misc/druplicon.png', 'public://', 0);
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();
    $this->markEntityForCleanup($file);
    return $file;
  }

  /**
   * Tests that references change only with other targets.
   */
  public function testReferenceChanges(): void {

    $project = $this->createProject(ProjectState::Pending);
    $project->set('field_image', ['target_id' => $this->createImage()->id(), 'alt' => 'Alt'])->save();
    $this->assertSame(['field_image'], $project->getChangedContentFields());
    $revision_count = count($this->revisionIds($project));

    // The same file with other derived values and alt text is no change.
    $file_id = $project->get('field_image')->target_id;
    $project->set('field_image', ['target_id' => $file_id, 'alt' => 'Other alt'])->save();
    $this->assertSame([], $project->getChangedContentFields());
    $this->assertCount($revision_count, $this->revisionIds($project));

    $project->set('field_image', ['target_id' => $this->createImage()->id(), 'alt' => 'Alt'])->save();
    $this->assertSame(['field_image'], $project->getChangedContentFields());

    $skill = Term::create(['vid' => 'skills', 'name' => $this->randomMachineName()]);
    $skill->save();
    $this->markEntityForCleanup($skill);
    $project->set('field_skills', [$skill->id()])->save();
    $this->assertSame(['field_skills'], $project->getChangedContentFields());
    $this->assertCount($revision_count + 2, $this->revisionIds($project));
  }

  /**
   * Tests that an explicit changed time dates the revision.
   */
  public function testExplicitChangedDatesRevision(): void {
    $project = $this->createProject(ProjectState::Pending);
    $project->setTitle('Changed')->set('changed', 1700000000)->save();
    $this->assertSame(1700000000, (int) $project->getRevisionCreationTime());
  }

}
