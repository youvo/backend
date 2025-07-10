<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\Plugin\rest\resource\Action\ProjectHideResource;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project hide resource.
 */
#[CoversMethod(ProjectHideResource::class, 'create')]
#[CoversMethod(ProjectHideResource::class, 'routes')]
#[CoversMethod(ProjectHideResource::class, 'access')]
#[CoversMethod(ProjectHideResource::class, 'post')]
#[Group('projects')]
class ProjectHideResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project hide resource - standard workflow.
   */
  public function testProjectHide(): void {

    $project = $this->createProject(ProjectState::Open);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/hide';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project hidden."', $response->getContent());
  }

  /**
   * Tests the for the project hide resource - not eligible.
   */
  public function testProjectHideNotEligible(): void {

    $project = $this->createProject();
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/hide';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('Project can not be hidden.', $response->getContent());
  }

  /**
   * Tests the for the project hide resource - no permission.
   */
  public function testProjectHideNoPermission(): void {

    $project = $this->createProject(ProjectState::Open);
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/hide';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:hide\' permission is required.', $response->getContent());
  }

}
