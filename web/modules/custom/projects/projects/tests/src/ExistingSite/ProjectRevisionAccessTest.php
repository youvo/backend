<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that only staff see project revisions and nobody reverts them.
 */
#[Group('projects')]
class ProjectRevisionAccessTest extends ProjectResourceTestBase {

  /**
   * Creates a pending project with a second revision.
   *
   * @return array{\Drupal\projects\ProjectInterface, int}
   *   The project and the ID of its first revision.
   */
  protected function createRevisedProject(): array {
    $project = $this->createProject(ProjectState::Pending);
    $first_revision_id = (int) $project->getRevisionId();
    $project->setTitle('Second title')->save();
    return [$project, $first_revision_id];
  }

  /**
   * Loads a revision of a project.
   */
  protected function loadRevision(int $revision_id): ProjectInterface {
    /** @var \Drupal\projects\ProjectInterface $revision */
    $revision = $this->container->get('entity_type.manager')->getStorage('project')->loadRevision($revision_id);
    return $revision;
  }

  /**
   * Checks access to an operation, without the static access cache.
   */
  protected function hasAccess(ProjectInterface $project, string $operation, AccountInterface $account): bool {
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();
    return $project->access($operation, $account);
  }

  /**
   * Tests that staff view revisions and everybody else doesn't.
   */
  public function testStaffViewRevisions(): void {

    [$project, $first_revision_id] = $this->createRevisedProject();
    $revision = $this->loadRevision($first_revision_id);
    $manager = $project->getOwner()->getManager();

    $staff = [
      'manager' => $manager,
      'supervisor' => $this->createSupervisor(),
      'observer' => $this->createCreative('observer'),
    ];
    foreach ($staff as $label => $account) {
      $this->assertTrue($this->hasAccess($revision, 'view revision', $account), $label);
      $this->assertTrue($this->hasAccess($revision, 'view all revisions', $account), $label);
    }

    $others = [
      'organization' => $project->getOwner(),
      'other manager' => $this->createManager(),
      'creative' => $this->createCreative(),
    ];
    foreach ($others as $label => $account) {
      $this->assertFalse($this->hasAccess($revision, 'view revision', $account), $label);
      $this->assertFalse($this->hasAccess($revision, 'view all revisions', $account), $label);
    }
  }

  /**
   * Tests that nobody reverts or deletes revisions, administrators included.
   */
  public function testRevertAndDeleteForbidden(): void {

    [, $first_revision_id] = $this->createRevisedProject();
    $revision = $this->loadRevision($first_revision_id);

    foreach (['supervisor' => $this->createSupervisor(), 'administrator' => $this->createCreative('administrator')] as $label => $account) {
      $this->assertFalse($this->hasAccess($revision, 'revert', $account), $label);
      $this->assertFalse($this->hasAccess($revision, 'delete revision', $account), $label);
    }
  }

  /**
   * Tests that revisions of archived projects are for administrators only.
   */
  public function testArchivedRevisionForbidden(): void {

    [$project, $first_revision_id] = $this->createRevisedProject();
    $project->setArchived()->save();
    $revision = $this->loadRevision($first_revision_id);

    $this->assertFalse($this->hasAccess($revision, 'view revision', $this->createCreative('observer')));
    $this->assertTrue($this->hasAccess($revision, 'view revision', $this->createSupervisor()));
  }

  /**
   * Tests the revision page.
   */
  public function testRevisionRoute(): void {

    [$project, $first_revision_id] = $this->createRevisedProject();
    $first_title = $this->loadRevision($first_revision_id)->getTitle();
    $path = '/projects/' . $project->id() . '/revisions/' . $first_revision_id . '/view';

    $this->drupalLogin($this->createSupervisor());
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($first_title);

    $this->drupalLogin($project->getOwner());
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that JSON:API serves no earlier revision to the organization.
   */
  public function testJsonApiResourceVersion(): void {

    [$project, $first_revision_id] = $this->createRevisedProject();

    $request = Request::create('/api/projects/' . $project->uuid(), 'GET', ['resourceVersion' => 'id:' . $first_revision_id]);
    $request->headers->set('Accept', 'application/vnd.api+json');
    $this->authenticateRequest($request, $project->getOwner());
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();
    $response = $this->doRequest($request);

    $this->assertSame(403, $response->getStatusCode());
  }

}
