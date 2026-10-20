<?php

namespace Drupal\Tests\organizations\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\organizations\Controller\OrganizationController;
use Drupal\organizations\Entity\Organization;
use Drupal\organizations\Form\OrganizationInviteForm;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\OneTimeAuthentication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
    $organization->activate();
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
    $this->assertMatchesRegularExpression('#/invite/' . $organization->id() . '/\d+/[\w-]+#', $event->getLink());
  }

  /**
   * The notify event of a prospect gets the same one-time link.
   */
  public function testNotifyEventOfProspectGetsOneTimeLink(): void {
    $organization = $this->createProspectProject();
    $event = new ProjectNotifyEvent($this->project);
    $this->eventDispatcher->dispatch($event);
    $this->assertMatchesRegularExpression('#/invite/' . $organization->id() . '/\d+/[\w-]+#', $event->getLink());
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

  /**
   * Declines the project of the prospect.
   */
  protected function declineProspectProject(Organization $organization): void {
    $this->project->set('field_lifecycle', ProjectState::Declined->value);
    $this->project->save();
    $this->assertTrue($organization->hasRoleProspect());
  }

  /**
   * Calls the invite controller with a session in the request.
   */
  protected function callInviteController(Organization $organization): mixed {
    $request = new Request();
    $request->setSession(new Session());
    return OrganizationController::create($this->container)
      ->invite((int) $organization->id(), $this->container->get('datetime.time')->getRequestTime(), 'hash', $request);
  }

  /**
   * A prospect with a draft project passes the invite controller.
   */
  public function testInviteLinkAcceptedWithDraft(): void {
    $organization = $this->createProspectProject();
    $this->project->save();
    $response = $this->callInviteController($organization);
    $this->assertStringContainsString('/' . $organization->id(), $response->getTargetUrl());
  }

  /**
   * The invite link of a prospect with a declined project is rejected.
   */
  public function testInviteLinkRejectedAfterDecline(): void {
    $organization = $this->createProspectProject();
    $this->project->save();
    $this->declineProspectProject($organization);
    $this->expectException(AccessDeniedHttpException::class);
    $this->callInviteController($organization);
  }

  /**
   * The invite form of a prospect with a declined project is rejected.
   */
  public function testInviteFormRejectedAfterDecline(): void {
    $organization = $this->createProspectProject();
    $this->project->save();
    $this->declineProspectProject($organization);
    // A valid link in the session must not matter without a draft project.
    $session = $this->container->get('session');
    $session->set('organization_invite_timeout', $this->container->get('datetime.time')->getRequestTime());
    $session->set('organization_invite_hash', $this->container->get(OneTimeAuthentication::class)->generateHmac($organization, $this->container->get('datetime.time')->getRequestTime()));
    $form = OrganizationInviteForm::create($this->container);
    $this->expectException(AccessDeniedHttpException::class);
    $form->buildForm([], new FormState(), $organization);
  }

}
