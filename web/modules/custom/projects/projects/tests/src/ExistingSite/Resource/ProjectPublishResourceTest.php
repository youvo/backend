<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project publish resource.
 */
#[Group('projects')]
class ProjectPublishResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project publish resource - standard workflow.
   */
  public function testProjectPublish(): void {

    $project = $this->createProject(ProjectState::Pending);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project published."', $response->getContent());
    $this->assertLastTransition($this->reloadProject($project), ProjectTransition::Publish, ProjectState::Pending, ProjectState::Open);
  }

  /**
   * Tests the for the project publish resource - supervisor.
   */
  public function testProjectPublishSupervisor(): void {

    $project = $this->createProject(ProjectState::Pending);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project published."', $response->getContent());
    $this->assertTrue($this->reloadProject($project)->lifecycle()->isOpen());
  }

  /**
   * Tests the for the project publish resource - not pending.
   */
  public function testProjectPublishNotPending(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('Project can not be published.', $response->getContent());
  }

  /**
   * Tests the for the project submit resource - not manager.
   */
  public function testProjectPublishNotManager(): void {

    $project = $this->createProject();
    $other_manager = $this->createManager();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());
  }

  /**
   * Tests the project publish resource - no permission.
   */
  public function testProjectPublishNoPermission(): void {

    $project = $this->createProject();
    $organization = $this->createOrganization();

    $path = '/api/projects/' . $project->uuid() . '/publish';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:publish\' permission is required.', $response->getContent());
  }

}
