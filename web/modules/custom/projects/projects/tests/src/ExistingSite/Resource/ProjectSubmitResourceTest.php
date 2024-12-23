<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project submit resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\ProjectSubmitResource
 * @group projects
 */
class ProjectSubmitResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project submit resource - standard workflow.
   *
   * @covers ::create
   * @covers ::routes
   * @covers ::access
   * @covers ::post
   */
  public function testProjectSubmit(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/submit';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project submitted."', $response->getContent());
  }

  /**
   * Tests the for the project submit resource - supervisor.
   *
   * @covers ::access
   */
  public function testProjectSubmitSupervisor(): void {

    $project = $this->createProject();
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/submit';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project submitted."', $response->getContent());
  }

  /**
   * Tests the for the project submit resource - not draft.
   *
   * @covers ::post
   */
  public function testProjectSubmitNotDraft(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/submit';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('{"message":"Project can not be submitted."}', $response->getContent());
  }

  /**
   * Tests the for the project submit resource - not owner.
   *
   * @covers ::access
   */
  public function testProjectSubmitNotOwner(): void {

    $project = $this->createProject();
    $other_organization = $this->createOrganization();

    $path = '/api/projects/' . $project->uuid() . '/submit';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this transition are not met."}', $response->getContent());
  }

  /**
   * Tests the for the project submit resource - not published.
   *
   * @covers ::access
   */
  public function testProjectSubmitNotPublished(): void {

    $project = $this->createProject();
    $project->setUnpublished();
    $project->save();
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/submit';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this transition are not met."}', $response->getContent());
  }

  /**
   * Tests the project submit resource - no permission.
   *
   * @covers ::access
   */
  public function testProjectSubmitNoPermission(): void {

    $project = $this->createProject();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/submit';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The \u0027restful post project:submit\u0027 permission is required."}', $response->getContent());
  }

}
