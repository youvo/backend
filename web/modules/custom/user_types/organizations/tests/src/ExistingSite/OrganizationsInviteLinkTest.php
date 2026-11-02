<?php

namespace Drupal\Tests\organizations\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Controller\OrganizationController;
use Drupal\organizations\Entity\Organization;
use Drupal\organizations\EventSubscriber\OrganizationsProjectNotifySubscriber;
use Drupal\organizations\Form\OrganizationInviteForm;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\user\OneTimeAuthentication;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the invite link of prospects and its use.
 *
 * The subscriber is called directly: dispatching the events on the site would
 * also send mails. Its subscription is checked on the dispatcher.
 */
#[Group('organizations')]
class OrganizationsInviteLinkTest extends ExistingSiteBase {

  /**
   * Invite and notify events of prospects get a one-time link.
   *
   * The notify event of an organization links to the draft.
   */
  public function testEventLinks(): void {

    $project = $this->createProject(ProjectState::Draft, Organization::ROLE_PROSPECT);
    $organization = $project->getOwner();
    $this->assertTrue($organization->hasRoleProspect());
    $subscriber = $this->container->get('organizations.project_notify.event_subscriber');
    $this->assertInstanceOf(OrganizationsProjectNotifySubscriber::class, $subscriber);
    $dispatcher = $this->container->get('event_dispatcher');
    $this->assertSame(100, $dispatcher->getListenerPriority(ProjectInviteEvent::class, [$subscriber, 'process']));
    $this->assertSame(100, $dispatcher->getListenerPriority(ProjectNotifyEvent::class, [$subscriber, 'process']));
    $one_time_link = '#/invite/' . $organization->id() . '/\d+/[\w-]+#';

    $event = new ProjectInviteEvent($project);
    $subscriber->process($event);
    $this->assertMatchesRegularExpression($one_time_link, $event->getLink());

    $event = new ProjectNotifyEvent($project);
    $subscriber->process($event);
    $this->assertMatchesRegularExpression($one_time_link, $event->getLink());

    $project = $this->createProject(ProjectState::Draft);
    $event = new ProjectNotifyEvent($project);
    $subscriber->process($event);
    $config = $this->container->get('config.factory');
    $base = $config->get('oauth_grant.settings')->get('local')
      ? 'http://localhost:3000'
      : rtrim((string) $config->get('youvo.settings')->get('hub_url'), '/');
    $this->assertSame($base . '/draft/' . $project->uuid(), $event->getLink());
  }

  /**
   * The invite link and form need a prospect with a draft project.
   */
  public function testInviteNeedsDraftProject(): void {

    $project = $this->createProject(ProjectState::Draft, Organization::ROLE_PROSPECT);
    $organization = $project->getOwner();
    $this->assertInstanceOf(Organization::class, $organization);

    // A prospect with a draft project passes the invite controller.
    $response = $this->callInviteController($organization);
    $this->assertStringContainsString('/' . $organization->id(), $response->getTargetUrl());

    // After the decline, the link and the form are rejected. A valid link in
    // the session must not matter without a draft project.
    $project->set('field_lifecycle', ProjectState::Declined->value)->save();
    $this->assertTrue($organization->hasRoleProspect());
    try {
      $this->callInviteController($organization);
      $this->fail('The invite link is rejected after the decline.');
    }
    catch (AccessDeniedHttpException) {
      // Expected.
    }
    $time = $this->container->get('datetime.time')->getRequestTime();
    $session = $this->container->get('session');
    $session->set('organization_invite_timeout', $time);
    $session->set('organization_invite_hash', $this->container->get(OneTimeAuthentication::class)->generateHmac($organization, $time));
    $this->expectException(AccessDeniedHttpException::class);
    OrganizationInviteForm::create($this->container)->buildForm([], new FormState(), $organization);
  }

  /**
   * Creates a project of a new organization with the role.
   */
  protected function createProject(ProjectState $state = ProjectState::Draft, string $role = 'organization'): ProjectInterface {

    $manager = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);

    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.org',
      'field_manager' => $manager,
      'status' => 1,
    ]);
    $organization->addRole($role);
    $organization->save();
    $this->markEntityForCleanup($organization);

    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $this->randomMachineName(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());
    // The project of an organization is logged as created.
    $log_storage = $this->container->get('entity_type.manager')->getStorage('log');
    $log_ids = $log_storage->getQuery()->accessCheck(FALSE)->condition('project', $project->id())->execute();
    foreach ($log_storage->loadMultiple($log_ids) as $log) {
      $this->markEntityForCleanup($log);
    }
    return $project;
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

}
