<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\projects\Event\ProjectChangeEvent;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that content changes after draft dispatch the project change event.
 */
#[Group('projects')]
class ProjectChangeEventTest extends ProjectResourceTestBase {

  /**
   * The dispatched project change events.
   *
   * @var \Drupal\projects\Event\ProjectChangeEvent[]
   */
  protected array $events = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('event_dispatcher')->addListener(ProjectChangeEvent::class, function (ProjectChangeEvent $event): void {
      $this->events[] = $event;
    });
  }

  /**
   * Tests that content changes in draft dispatch nothing.
   */
  public function testDraftEditDispatchesNothing(): void {
    $project = $this->createProject();
    $project->setTitle('Changed')->save();
    $this->assertSame([], $this->events);
  }

  /**
   * Tests that the save that submits a project dispatches nothing.
   */
  public function testSubmitSaveDispatchesNothing(): void {
    $project = $this->createProject();
    $project->setTitle('Changed');
    $project->lifecycle()->submit();
    $project->save();
    $this->assertTrue($project->lifecycle()->isPending());
    $this->assertSame([], $this->events);
  }

  /**
   * Tests that content changes after draft dispatch the event.
   */
  public function testEditAfterSubmitDispatches(): void {

    $states = [
      ProjectState::Pending,
      ProjectState::Open,
      ProjectState::Ongoing,
      ProjectState::Completed,
      ProjectState::Declined,
    ];
    foreach ($states as $state) {
      $this->events = [];
      $project = $this->createProject($state);
      $previous_revision_id = (int) $project->getRevisionId();

      $project->setTitle('Changed')->set('field_city', 'Hamburg')->save();

      $this->assertCount(1, $this->events, $state->value);
      $event = $this->events[0];
      $this->assertSame($project->id(), $event->getProject()->id(), $state->value);
      $this->assertSame(['title', 'field_city'], $event->getFields(), $state->value);
      $this->assertSame($previous_revision_id, $event->getPreviousRevisionId(), $state->value);
      $this->assertSame((int) $project->getRevisionId(), $event->getRevisionId(), $state->value);
      $this->assertNotSame($previous_revision_id, $event->getRevisionId(), $state->value);
    }
  }

  /**
   * Tests that workflow saves dispatch nothing.
   */
  public function testWorkflowSaveDispatchesNothing(): void {
    $project = $this->createProject(ProjectState::Pending);
    $project->appendApplicant($this->createCreative())->save();
    $this->assertSame([], $this->events);
  }

  /**
   * Tests that the organization editing via JSON:API dispatches the event.
   */
  public function testOrganizationPatchDispatches(): void {

    $project = $this->createProject(ProjectState::Pending);
    $organization = $project->getOwner();

    $data = ['type' => 'project', 'id' => $project->uuid(), 'attributes' => ['title' => 'Edited']];
    $request = Request::create('/api/projects/' . $project->uuid(), 'PATCH', [], [], [], [], Json::encode(['data' => $data]));
    $request->headers->set('Content-Type', 'application/vnd.api+json');
    $request->headers->set('Accept', 'application/vnd.api+json');
    $this->authenticateRequest($request, $organization);
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();
    $response = $this->doRequest($request);

    $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    $this->assertCount(1, $this->events);
    $this->assertSame(['title'], $this->events[0]->getFields());
    $revision = $this->reloadProject($project);
    $this->assertSame('Edited', $revision->getTitle());
    $this->assertSame((int) $organization->id(), (int) $revision->getRevisionUserId());
  }

}
