<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\Component\Serialization\Json;
use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project mediate resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\ProjectMediateResource
 * @group projects
 */
class ProjectMediateResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the project mediate resource - standard GET workflow.
   *
   * @covers ::create
   * @covers ::routes
   * @covers ::access
   * @covers ::get
   */
  public function testProjectMediateGet(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $organization = $project->getOwner();
    $applicant = $this->createCreative();
    $project->appendApplicant($applicant);
    $project->save();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'GET');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString($project->uuid(), $response->getContent());
    $this->assertStringContainsString($applicant->uuid(), $response->getContent());
  }

  /**
   * Tests the project mediate resource - standard POST workflow.
   *
   * @covers ::post
   * @covers ::loadSelectedCreatives
   */
  public function testProjectMediate(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $body = [
      'selected_creatives' => [
        $this->createCreative()->uuid(),
        $this->createCreative()->uuid(),
        $this->createCreative()->uuid(),
      ],
    ];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project mediated."', $response->getContent());
  }

  /**
   * Tests the project mediate resource - manager.
   *
   * @covers ::access
   */
  public function testProjectMediateManager(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $body = ['selected_creatives' => [$this->createCreative()->uuid()]];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project mediated."', $response->getContent());
  }

  /**
   * Tests the project mediate resource - not open.
   *
   * @covers ::post
   */
  public function testProjectMediateNotOpen(): void {

    $project = $this->createProject(ProjectState::COMPLETED);
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $body = ['selected_creatives' => [$this->createCreative()->uuid()]];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('{"message":"Project can not be mediated."}', $response->getContent());
  }

  /**
   * Tests the project mediate resource - supervisor.
   *
   * @covers ::access
   */
  public function testProjectMediateSupervisor(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $body = ['selected_creatives' => [$this->createCreative()->uuid()]];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project mediated."', $response->getContent());
  }

  /**
   * Tests the project mediate resource - not owner.
   *
   * @covers ::access
   */
  public function testProjectMediateNotOwner(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $other_organization = $this->createOrganization();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this transition are not met."}', $response->getContent());
  }

  /**
   * Tests the project mediate resource - not manager.
   *
   * @covers ::access
   */
  public function testProjectMediateNotManager(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $other_manager = $this->createManager();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this transition are not met."}', $response->getContent());
  }

  /**
   * Tests the project mediate resource - not published (status).
   *
   * @covers ::access
   */
  public function testProjectMediateNotPublished(): void {

    $project = $this->createProject();
    $project->setUnpublished();
    $project->save();
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The project conditions for this transition are not met."}', $response->getContent());
  }

  /**
   * Tests the project mediate resource - no permission.
   *
   * @covers ::access
   */
  public function testProjectMediateNoPermission(): void {

    $project = $this->createProject();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('{"message":"The \u0027restful post project:mediate\u0027 permission is required."}', $response->getContent());
  }

}
