<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project reset resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\Transition\ProjectResetResource
 * @group projects
 */
class ProjectResetResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project reset resource - standard workflow.
   *
   * @covers ::create
   * @covers ::routes
   * @covers ::access
   * @covers ::post
   */
  public function testProjectReset(): void {

    $project = $this->createProject(ProjectState::Open);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/reset';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project reset."', $response->getContent());
  }

  /**
   * Tests the for the project reset resource - no permission.
   *
   * @covers ::access
   */
  public function testProjectResetNoPermission(): void {

    $project = $this->createProject();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/reset';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:reset\' permission is required.', $response->getContent());
  }

}
