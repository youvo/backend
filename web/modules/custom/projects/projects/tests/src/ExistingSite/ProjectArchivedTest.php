<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\lifecycle\Exception\LifecycleTransitionException;
use Drupal\manager\Plugin\ManagerRule\ManagerRuleArchived;
use Drupal\projects\Access\ProjectEntityAccess;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests that archived projects don't transition or act and are read-only.
 */
#[CoversClass(ProjectLifecycle::class)]
#[CoversClass(ProjectEntityAccess::class)]
#[CoversClass(ManagerRuleArchived::class)]
#[Group('projects')]
class ProjectArchivedTest extends ProjectResourceTestBase {

  /**
   * The transition resources with a state they would start from.
   */
  protected const array TRANSITIONS = [
    'submit' => ProjectState::Draft,
    'publish' => ProjectState::Pending,
    'mediate' => ProjectState::Open,
    'complete' => ProjectState::Ongoing,
    'decline' => ProjectState::Pending,
    'reset' => ProjectState::Open,
  ];

  /**
   * The action resources that refuse archived projects in access().
   */
  protected const array ACTIONS = ['hide', 'show', 'promote', 'demote'];

  /**
   * Creates an archived project.
   */
  protected function createArchivedProject(ProjectState $state): ProjectInterface {
    $project = $this->createProject($state);
    $project->setArchived()->save();
    return $project;
  }

  /**
   * Posts to a project resource.
   */
  protected function post(ProjectInterface $project, string $resource, AccountInterface $account): Response {
    $request = Request::create('/api/projects/' . $project->uuid() . '/' . $resource, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $account);
    return $this->doRequest($request);
  }

  /**
   * Patches the title of a project via JSON:API.
   */
  protected function patchTitle(ProjectInterface $project, AccountInterface $account): Response {
    $data = ['type' => 'project', 'id' => $project->uuid(), 'attributes' => ['title' => 'Edited']];
    $request = Request::create('/api/projects/' . $project->uuid(), 'PATCH', [], [], [], [], Json::encode(['data' => $data]));
    $request->headers->set('Content-Type', 'application/vnd.api+json');
    $request->headers->set('Accept', 'application/vnd.api+json');
    $this->authenticateRequest($request, $account);
    // Requests are handled in this process: reset the static access caches.
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();
    return $this->doRequest($request);
  }

  /**
   * Tests that the lifecycle refuses every transition of archived projects.
   */
  public function testLifecycleRefusesTransitions(): void {

    foreach (static::TRANSITIONS as $transition => $state) {
      $lifecycle = $this->createArchivedProject($state)->lifecycle();
      try {
        $lifecycle->{$transition}();
        $this->fail($transition);
      }
      catch (LifecycleTransitionException) {
        $this->assertTrue($lifecycle->project()->isArchived(), $transition);
      }
    }

    $this->assertFalse($this->createArchivedProject(ProjectState::Pending)->lifecycle()->canDecline());
  }

  /**
   * Tests that the transition resources refuse archived projects.
   */
  public function testTransitionResourcesRefuse(): void {
    $supervisor = $this->createSupervisor();
    foreach (static::TRANSITIONS as $transition => $state) {
      $response = $this->post($this->createArchivedProject($state), $transition, $supervisor);
      $this->assertSame(403, $response->getStatusCode(), $transition);
      $this->assertSame('The project is archived.', $response->getContent(), $transition);
    }
  }

  /**
   * Tests that the action resources refuse archived projects.
   */
  public function testActionResourcesRefuse(): void {

    $supervisor = $this->createSupervisor();
    foreach (static::ACTIONS as $action) {
      $response = $this->post($this->createArchivedProject(ProjectState::Open), $action, $supervisor);
      $this->assertSame(403, $response->getStatusCode(), $action);
    }

    $response = $this->post($this->createArchivedProject(ProjectState::Draft), 'notify', $supervisor);
    $this->assertSame(403, $response->getStatusCode(), 'notify');

    $response = $this->post($this->createArchivedProject(ProjectState::Open), 'apply', $this->createCreative());
    $this->assertSame(403, $response->getStatusCode(), 'apply');
  }

  /**
   * Tests that only administrators edit archived projects.
   */
  public function testArchivedReadOnly(): void {

    $project = $this->createArchivedProject(ProjectState::Open);
    $this->assertSame(403, $this->patchTitle($project, $this->createSupervisor())->getStatusCode());
    $this->assertSame(200, $this->patchTitle($project, $this->createCreative('administrator'))->getStatusCode());

    // Active projects stay editable for supervisors.
    $project = $this->createProject(ProjectState::Open);
    $this->assertSame(200, $this->patchTitle($project, $this->createSupervisor())->getStatusCode());
  }

  /**
   * Tests that the archived manager rule suppresses the other rules.
   */
  public function testManagerRule(): void {

    $project = $this->createArchivedProject(ProjectState::Open);
    $project->setHidden()->save();
    $rules = $this->container->get('plugin.manager.manager_rules')->getRules($project);
    $this->assertCount(1, $rules);
    $this->assertInstanceOf(ManagerRuleArchived::class, reset($rules));

    $project->setArchived(FALSE)->save();
    $rules = $this->container->get('plugin.manager.manager_rules')->getRules($project);
    $this->assertNotInstanceOf(ManagerRuleArchived::class, reset($rules));
  }

}
