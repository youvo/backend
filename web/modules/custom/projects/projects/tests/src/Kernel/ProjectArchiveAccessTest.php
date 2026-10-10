<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\projects\Access\ProjectEntityAccess;
use Drupal\projects\Access\ProjectFieldAccess;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the entity access for archived projects.
 */
#[CoversMethod(ProjectEntityAccess::class, 'checkAccess')]
#[CoversMethod(ProjectFieldAccess::class, 'checkFieldAccess')]
#[Group('projects')]
class ProjectArchiveAccessTest extends ProjectEventSubscriberTestBase {

  /**
   * Tests that archiving revokes the access of other users.
   *
   * Open and ongoing projects are visible to every creative. Archived projects
   * remain visible to the organization, but not to anyone else, even though the
   * project stays published.
   */
  #[DataProvider('visibleStateProvider')]
  public function testArchivedProjectAccess(ProjectState $state): void {

    $project = $this->createProject($state);
    $project->save();
    $creative = $this->createCreative();
    $organization = $project->getOwner();

    $this->assertTrue($project->access('view', $creative));
    $this->assertTrue($project->access('view', $organization));

    $this->eventDispatcher->dispatch(new ProjectArchiveEvent($project));
    $this->assertTrue($project->isPublished());

    // The access results are cached statically per entity and account.
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();

    $this->assertFalse($project->access('view', $creative));
    $this->assertTrue($project->access('view', $organization));
    $this->assertFalse($project->access('update', $organization));
    $this->assertFalse($project->access('delete', $organization));
  }

  /**
   * Provides the states in which other users may view the project.
   */
  public static function visibleStateProvider(): array {
    return [
      ProjectState::Open->value => [ProjectState::Open],
      ProjectState::Ongoing->value => [ProjectState::Ongoing],
    ];
  }

  /**
   * Tests that the organization can view applicants and participants.
   *
   * Archived projects retain the applicants and participants of the state they
   * were archived from. Only the author may view them, not other creatives.
   */
  #[DataProvider('applicantsAndParticipantsProvider')]
  public function testArchivedProjectFieldAccess(string $field_name): void {

    $project = $this->createProject(ProjectState::Archived);
    $creative = $this->createCreative();
    $organization = $project->getOwner();
    $field = $project->getFieldDefinition($field_name);

    $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $organization);
    $this->assertFalse($result->isForbidden());

    $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $creative);
    $this->assertTrue($result->isForbidden());
  }

  /**
   * Provides the fields that are restricted to authors and managers.
   */
  public static function applicantsAndParticipantsProvider(): array {
    return [
      'applicants' => ['field_applicants'],
      'participants' => ['field_participants'],
    ];
  }

}
