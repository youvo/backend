<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project reset resource.
 */
#[Group('projects')]
class ProjectResetResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project reset resource - standard workflow.
   */
  public function testProjectReset(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setPromoted(TRUE)->setHidden()->save();
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/reset';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project reset."', $response->getContent());

    // The reset draft is neither promoted nor hidden.
    $stored = $this->reloadProject($project);
    $this->assertLastTransition($stored, ProjectTransition::Reset, ProjectState::Open, ProjectState::Draft);
    $this->assertFalse($stored->isPromoted());
    $this->assertFalse($stored->isHidden());
  }

  /**
   * Tests the for the project reset resource - no permission.
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
