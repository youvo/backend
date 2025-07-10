<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\Plugin\rest\resource\ProjectPromoteResource;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project promote resource.
 */
#[CoversMethod(ProjectPromoteResource::class, 'create')]
#[CoversMethod(ProjectPromoteResource::class, 'routes')]
#[CoversMethod(ProjectPromoteResource::class, 'access')]
#[CoversMethod(ProjectPromoteResource::class, 'post')]
#[Group('projects')]
class ProjectPromoteResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project promote resource - standard workflow.
   */
  public function testProjectPromote(): void {

    $project = $this->createProject(ProjectState::Open);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/promote';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project promoted."', $response->getContent());
  }

  /**
   * Tests the for the project promote resource - no permission.
   */
  public function testProjectPromoteNoPermission(): void {

    $project = $this->createProject();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/promote';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:promote\' permission is required.', $response->getContent());
  }

}
