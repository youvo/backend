<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\Plugin\rest\resource\Action\ProjectDemoteResource;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project demote resource.
 */
#[CoversMethod(ProjectDemoteResource::class, 'create')]
#[CoversMethod(ProjectDemoteResource::class, 'routes')]
#[CoversMethod(ProjectDemoteResource::class, 'access')]
#[CoversMethod(ProjectDemoteResource::class, 'post')]
#[Group('projects')]
class ProjectDemoteResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project demote resource - standard workflow.
   */
  public function testProjectDemote(): void {

    $project = $this->createProject(ProjectState::Open);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/demote';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project demoted."', $response->getContent());
  }

  /**
   * Tests the for the project demote resource - no permission.
   */
  public function testProjectDemoteNoPermission(): void {

    $project = $this->createProject();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/demote';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:demote\' permission is required.', $response->getContent());
  }

}
