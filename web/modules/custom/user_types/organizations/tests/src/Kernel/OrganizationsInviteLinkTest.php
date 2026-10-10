<?php

namespace Drupal\Tests\organizations\Kernel;

use Drupal\organizations\Entity\Organization;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the link set on invite and notify events.
 *
 * @group organizations
 */
class OrganizationsInviteLinkTest extends ProjectEventSubscriberTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'language',
    'system',
    'youvo',
  ];

  /**
   * The project of the prospect.
   *
   * @var \Drupal\projects\ProjectInterface
   */
  protected $project;

  /**
   * Makes the owner of a new project a prospect.
   */
  protected function createProspectProject(): Organization {
    $project = $this->createProject(ProjectState::Draft);
    $organization = $project->getOwner();
    Role::create(['id' => Organization::ROLE_PROSPECT, 'label' => 'Prospect'])->save();
    $organization->addRole(Organization::ROLE_PROSPECT);
    $organization->save();
    $this->assertTrue($organization->hasRoleProspect());
    $this->project = $project;
    return $organization;
  }

  /**
   * The invite event gets a one-time organization link.
   */
  public function testInviteEventGetsOneTimeLink(): void {
    $organization = $this->createProspectProject();
    $event = new ProjectInviteEvent($this->project);
    $this->eventDispatcher->dispatch($event);
    $this->assertStringContainsString('/invite/' . $organization->id() . '/', $event->getLink());
  }

  /**
   * The notify event of a prospect gets the same one-time link.
   */
  public function testNotifyEventOfProspectGetsOneTimeLink(): void {
    $organization = $this->createProspectProject();
    $event = new ProjectNotifyEvent($this->project);
    $this->eventDispatcher->dispatch($event);
    $this->assertStringContainsString('/invite/' . $organization->id() . '/', $event->getLink());
  }

  /**
   * The notify event of an organization links to the draft.
   */
  public function testNotifyEventStillGetsDraftLink(): void {
    $project = $this->createProject(ProjectState::Draft);
    $project->save();
    $event = new ProjectNotifyEvent($project);
    $this->eventDispatcher->dispatch($event);
    $this->assertStringContainsString('/draft/' . $project->uuid(), $event->getLink());
  }

}
