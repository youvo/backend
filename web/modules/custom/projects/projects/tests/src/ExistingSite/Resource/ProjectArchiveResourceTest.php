<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\Component\Serialization\Json;
use Drupal\projects\Plugin\rest\resource\Transition\ProjectArchiveResource;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project archive resource.
 */
#[CoversMethod(ProjectArchiveResource::class, 'create')]
#[CoversMethod(ProjectArchiveResource::class, 'routes')]
#[CoversMethod(ProjectArchiveResource::class, 'access')]
#[CoversMethod(ProjectArchiveResource::class, 'post')]
#[CoversMethod(ProjectArchiveResource::class, 'validateRequestContent')]
#[Group('projects')]
class ProjectArchiveResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project archive resource - standard workflow.
   */
  public function testProjectArchive(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $body = ['reason' => 'external', 'message' => 'Mediated outside of the platform.'];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project archived."', $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Archived);
  }

  /**
   * Tests the for the project archive resource - without request body.
   */
  public function testProjectArchiveWithoutBody(): void {

    $project = $this->createProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project archived."', $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Archived);
  }

  /**
   * Tests the for the project archive resource - supervisor.
   */
  public function testProjectArchiveSupervisor(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project archived."', $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Archived);
  }

  /**
   * Tests the for the project archive resource - not archivable states.
   */
  #[DataProvider('notArchivableProvider')]
  public function testProjectArchiveNotArchivable(ProjectState $state): void {

    $project = $this->createProject($state);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('Project can not be archived.', $response->getContent());

    $this->assertProjectState($project->id(), $state);
  }

  /**
   * Provides the states from which the project can not be archived.
   */
  public static function notArchivableProvider(): array {
    return [
      ProjectState::Completed->value => [ProjectState::Completed],
      ProjectState::Archived->value => [ProjectState::Archived],
    ];
  }

  /**
   * Tests the for the project archive resource - invalid reason.
   */
  public function testProjectArchiveInvalidReason(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode(['reason' => 'boring']));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertStringContainsString('The reason in the request body is not valid.', $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Open);
  }

  /**
   * Tests the for the project archive resource - invalid message.
   */
  public function testProjectArchiveInvalidMessage(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode(['message' => ['not', 'a string']]));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertStringContainsString('The message in the request body must be a string.', $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Open);
  }

  /**
   * Tests the for the project archive resource - not manager.
   */
  public function testProjectArchiveNotManager(): void {

    $project = $this->createProject(ProjectState::Open);
    $other_manager = $this->createManager();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Open);
  }

  /**
   * Tests the for the project archive resource - not published (status).
   */
  public function testProjectArchiveNotPublished(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setUnpublished();
    $project->save();
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());
  }

  /**
   * Tests the project archive resource - no permission.
   */
  public function testProjectArchiveNoPermission(): void {

    $project = $this->createProject(ProjectState::Open);
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/archive';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:archive\' permission is required.', $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Open);
  }

  /**
   * Tests that an archived project can not enter the regular flow.
   */
  #[DataProvider('regularTransitionProvider')]
  public function testArchivedProjectRegularTransition(string $transition, string $message, string $actor): void {

    $project = $this->createProject(ProjectState::Archived);
    $account = $actor === 'manager' ? $project->getOwner()->getManager() : $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/' . $transition;
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $account);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals($message, $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Archived);
  }

  /**
   * Provides the regular transitions with the expected conflict message.
   */
  public static function regularTransitionProvider(): array {
    return [
      'publish' => ['publish', 'Project can not be published.', 'manager'],
      'submit' => ['submit', 'Project can not be submitted.', 'organization'],
    ];
  }

  /**
   * Tests that a supervisor can reset an archived project.
   */
  public function testArchivedProjectReset(): void {

    $project = $this->createProject(ProjectState::Archived);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/reset';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project reset."', $response->getContent());

    $this->assertProjectState($project->id(), ProjectState::Draft);
  }

  /**
   * Asserts the lifecycle state of the project in the database.
   */
  protected function assertProjectState(int|string|null $project_id, ProjectState $state): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('project');
    $storage->resetCache([$project_id]);
    /** @var \Drupal\projects\ProjectInterface $project */
    $project = $storage->load($project_id);
    $this->assertEquals($state->value, $project->get('field_lifecycle')->value);
  }

}
