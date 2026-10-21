<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\projects\Access\ProjectEntityAccess;
use Drupal\projects\Access\ProjectFieldAccess;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the entity access for declined projects.
 */
#[CoversMethod(ProjectEntityAccess::class, 'checkAccess')]
#[CoversMethod(ProjectFieldAccess::class, 'checkFieldAccess')]
#[Group('projects')]
class ProjectDeclineAccessTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The first user bypasses all permissions. Create it up front.
    $this->createCreative();
  }

  /**
   * Tests that declining revokes the access of other users.
   *
   * Declined projects remain visible to the organization and the manager, but
   * not to anyone else, even though the project stays published.
   */
  #[DataProvider('declinableStateProvider')]
  public function testDeclinedProjectAccess(ProjectState $state): void {

    $project = $this->createProject($state, $state === ProjectState::Draft);
    $project->save();
    $creative = $this->createCreative();
    $organization = $project->getOwner();
    $manager = $organization->getManager();
    $manager->addRole('manager')->save();

    $this->eventDispatcher->dispatch(new ProjectDeclineEvent($project));
    $this->assertTrue($project->isPublished());

    // The access results are cached statically per entity and account.
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();

    $this->assertFalse($project->access('view', $creative));
    $this->assertTrue($project->access('view', $organization));
    $this->assertFalse($project->access('update', $organization));
    $this->assertFalse($project->access('delete', $organization));
    $this->assertTrue($project->access('view', $manager));
    $this->assertTrue($project->access('update', $manager));
  }

  /**
   * Provides the states from which a project can be declined.
   */
  public static function declinableStateProvider(): array {
    return [
      ProjectState::Draft->value => [ProjectState::Draft],
      ProjectState::Pending->value => [ProjectState::Pending],
    ];
  }

  /**
   * Tests that the author can not view applicants and participants.
   */
  #[DataProvider('applicantsAndParticipantsProvider')]
  public function testDeclinedHidesApplicantFields(string $field_name): void {
    $project = $this->createProject(ProjectState::Declined);
    $organization = $project->getOwner();
    $field = $project->getFieldDefinition($field_name);
    $result = ProjectFieldAccess::checkFieldAccess($project, 'view', $field, $organization);
    $this->assertTrue($result->isForbidden());

    // In contrast, the author may view the field of an open project.
    $open = $this->createProject(ProjectState::Open);
    $result = ProjectFieldAccess::checkFieldAccess($open, 'view', $open->getFieldDefinition($field_name), $open->getOwner());
    $this->assertEquals($field_name === 'field_applicants', !$result->isForbidden());
  }

  /**
   * Provides the fields that are restricted to open and ongoing projects.
   */
  public static function applicantsAndParticipantsProvider(): array {
    return [
      'applicants' => ['field_applicants'],
      'participants' => ['field_participants'],
    ];
  }

}
