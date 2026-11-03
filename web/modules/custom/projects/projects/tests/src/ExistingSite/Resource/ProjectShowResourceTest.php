<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project show resource.
 */
#[Group('projects')]
class ProjectShowResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project show resource - standard workflow.
   */
  public function testProjectShow(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setHidden()->save();
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/show';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project shown."', $response->getContent());
    $this->assertFalse($this->reloadProject($project)->isHidden());
  }

  /**
   * Tests the for the project show resource - not eligible.
   */
  public function testProjectShowNotEligible(): void {

    $project = $this->createProject();
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/show';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this action are not met.', $response->getContent());
  }

  /**
   * Tests the for the project show resource - no permission.
   */
  public function testProjectShowNoPermission(): void {

    $project = $this->createProject(ProjectState::Open);
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/show';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:show\' permission is required.', $response->getContent());
  }

}
