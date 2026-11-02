<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project apply resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\Action\ProjectApplyResource
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

    $project = $this->createProject(ProjectState::Open);
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

    $project = $this->createProject(ProjectState::Open);
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
   * Tests the project apply resource - malformed bodies answer 400.
   */
  public function testProjectApplyMalformed(): void {
    $project = $this->createProject(ProjectState::Open);
    $creative = $this->createCreative();
    foreach (['"x"', '{"message":["x"]}', '{"phone":1}'] as $body) {
      $request = Request::create('/api/projects/' . $project->uuid() . '/apply', 'POST', [], [], [], [], $body);
      $request->headers->set('Content-Type', 'application/json');
      $this->authenticateRequest($request, $creative);
      $this->assertEquals(400, $this->doRequest($request)->getStatusCode(), $body);
    }
    $this->assertFalse($project->isApplicant($creative));
  }

  /**
   * Tests the project apply resource - already applied.
   *
   * @covers ::access
   */
  public function testProjectApplyAlreadyApplied(): void {

    $project = $this->createProject(ProjectState::Open);
    $creative = $this->createCreative();
    $project->appendApplicant($creative);
    $project->save();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The applicant conditions for this application are not met. The creative may already applied.', $response->getContent());
  }

  /**
   * Tests the project apply resource - manager.
   *
   * @covers ::access
   */
  public function testProjectApplyManager(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The applicant conditions for this application are not met. The creative may already applied.', $response->getContent());
  }

  /**
   * Tests the project apply resource - not open.
   *
   * @covers ::access
   */
  public function testProjectApplyNotOpen(): void {

    $project = $this->createProject(ProjectState::Pending);
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this application are not met.', $response->getContent());
  }

  /**
   * Tests the project apply resource - archived.
   *
   * @covers ::access
   */
  public function testProjectApplyArchived(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setArchived();
    $project->save();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this application are not met.', $response->getContent());
  }

  /**
   * Tests the for the project apply resource - hidden project.
   */
  public function testProjectApplyHidden(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setHidden();
    $project->save();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this application are not met.', $response->getContent());
  }

  /**
   * Tests the project apply resource - no permission.
   *
   * @covers ::access
   */
  public function testProjectApplyNoPermission(): void {

    $project = $this->createProject(ProjectState::Open);
    $organization = $this->createOrganization();

    $path = '/api/projects/' . $project->uuid() . '/apply';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:apply\' permission is required.', $response->getContent());
  }

}
