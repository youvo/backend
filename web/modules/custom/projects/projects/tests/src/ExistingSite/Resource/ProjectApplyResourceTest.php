<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project apply resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\ProjectApplyResource
 * @group projects
 */
class ProjectApplyResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the project apply resource - standard GET workflow.
   *
   * @covers ::create
   * @covers ::routes
   * @covers ::access
   * @covers ::get
   */
  public function testProjectApplyGet(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'GET');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"The user may apply to the project."', $response->getContent());
  }

  /**
   * Tests the project apply resource - standard POST workflow.
   *
   * @covers ::post
   */
  public function testProjectApply(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Application completed."', $response->getContent());
  }

  /**
   * Tests the project apply resource - already applied.
   *
   * @covers ::access
   */
  public function testProjectApplyAlreadyApplied(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $creative = $this->createCreative();
    $project->appendApplicant($creative);
    $project->save();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The applicant conditions for this application are not met. The creative may already applied."}', $response->getContent());
  }

  /**
   * Tests the project apply resource - manager.
   *
   * @covers ::access
   */
  public function testProjectApplyManager(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The applicant conditions for this application are not met. The creative may already applied."}', $response->getContent());
  }

  /**
   * Tests the project apply resource - not open.
   *
   * @covers ::access
   */
  public function testProjectApplyNotOpen(): void {

    $project = $this->createProject(ProjectState::PENDING);
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this application are not met."}', $response->getContent());
  }

  /**
   * Tests the project apply resource - not published (status).
   *
   * @covers ::access
   */
  public function testProjectApplyNotPublished(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $project->setUnpublished();
    $project->save();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this application are not met."}', $response->getContent());
  }

  /**
   * Tests the project apply resource - no permission.
   *
   * @covers ::access
   */
  public function testProjectApplyNoPermission(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $organization = $this->createOrganization();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The \u0027restful post project:apply\u0027 permission is required."}', $response->getContent());
  }

}
