<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project submit resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\ProjectPublishResource
 * @group projects
 */
class ProjectPublishResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project publish resource - standard workflow.
   *
   * @covers ::create
   * @covers ::routes
   * @covers ::access
   * @covers ::post
   */
  public function testProjectPublish(): void {

    $project = $this->createProject(ProjectState::PENDING);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project published."', $response->getContent());
  }

  /**
   * Tests the for the project publish resource - not pending.
   *
   * @covers ::post
   */
  public function testProjectPublishNotPending(): void {

    $project = $this->createProject(ProjectState::ONGOING);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('{"message":"Project can not be published."}', $response->getContent());
  }

  /**
   * Tests the for the project publish resource - supervisor.
   *
   * @covers ::access
   */
  public function testProjectPublishSupervisor(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project published."', $response->getContent());
  }

  /**
   * Tests the for the project submit resource - not manager.
   *
   * @covers ::access
   */
  public function testProjectPublishNotManager(): void {

    $project = $this->createProject();
    $other_manager = $this->createManager();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this transition are not met."}', $response->getContent());
  }

  /**
   * Tests the for the project publish resource - not published (status).
   *
   * @covers ::access
   */
  public function testProjectPublishNotPublished(): void {

    $project = $this->createProject();
    $project->setUnpublished();
    $project->save();
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this transition are not met."}', $response->getContent());
  }

  /**
   * Tests the project publish resource - no permission.
   *
   * @covers ::access
   */
  public function testProjectPublishNoPermission(): void {

    $project = $this->createProject();
    $organization = $this->createOrganization();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The \u0027restful post project:publish\u0027 permission is required."}', $response->getContent());
  }

}
