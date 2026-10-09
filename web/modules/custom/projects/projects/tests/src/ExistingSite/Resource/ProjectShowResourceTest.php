<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\Plugin\rest\resource\Action\ProjectShowResource;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project show resource.
 */
#[CoversMethod(ProjectShowResource::class, 'create')]
#[CoversMethod(ProjectShowResource::class, 'routes')]
#[CoversMethod(ProjectShowResource::class, 'access')]
#[CoversMethod(ProjectShowResource::class, 'post')]
#[Group('projects')]
class ProjectShowResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project show resource - standard workflow.
   */
  public function testProjectShow(): void {

    $project = $this->createProject(ProjectState::Open);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/show';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project shown."', $response->getContent());
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
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('Project can not be shown.', $response->getContent());
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
