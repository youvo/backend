<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project notify resource.
 */
#[Group('projects')]
class ProjectNotifyResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project notify resource - standard workflow.
   */
  public function testProjectNotify(): void {

    $project = $this->createProject();
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/notify';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project notified."', $response->getContent());
  }

  /**
   * Tests the for the project notify resource - prospect.
   */
  public function testProjectNotifyProspect(): void {

    $project = $this->createProject(ProjectState::Draft, 'prospect');
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/notify';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project invited."', $response->getContent());
  }

  /**
   * Tests the for the project notify resource - supervisor.
   */
  public function testProjectNotifySupervisor(): void {

    $project = $this->createProject();
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/notify';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project notified."', $response->getContent());
  }

  /**
   * Tests the project notify resource - supervisor outside the draft state.
   */
  public function testProjectNotifySupervisorNotDraft(): void {

    $project = $this->createProject(ProjectState::Open);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/notify';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this action are not met.', $response->getContent());
  }

  /**
   * Tests the for the project notify resource - not draft.
   */
  public function testProjectNotifyNotDraft(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/notify';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this action are not met.', $response->getContent());
  }

  /**
   * Tests the for the project notify resource - not manager.
   */
  public function testProjectNotifyNotManager(): void {

    $project = $this->createProject();
    $other_manager = $this->createManager();

    $path = '/api/projects/' . $project->uuid() . '/notify';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this action are not met.', $response->getContent());
  }

  /**
   * Tests the project notify resource - no permission.
   */
  public function testProjectNotifyNoPermission(): void {

    $project = $this->createProject();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/notify';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:notify\' permission is required.', $response->getContent());
  }

}
