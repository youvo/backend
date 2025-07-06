<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project notify resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\ProjectNotifyResource
 * @group projects
 */
class ProjectNotifyResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project notify resource - standard workflow.
   *
   * @covers ::create
   * @covers ::routes
   * @covers ::access
   * @covers ::post
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
    $this->assertEquals('"The organization was notified."', $response->getContent());
  }

  /**
   * Tests the for the project notify resource - prospect.
   *
   * @covers ::post
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
    $this->assertEquals('"The organization was invited."', $response->getContent());
  }

  /**
   * Tests the for the project notify resource - supervisor.
   *
   * @covers ::access
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
    $this->assertEquals('"The organization was notified."', $response->getContent());
  }

  /**
   * Tests the for the project notify resource - not draft.
   *
   * @covers ::access
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
   *
   * @covers ::access
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
   * Tests the for the project notify resource - not published.
   *
   * @covers ::access
   */
  public function testProjectNotifyNotPublished(): void {

    $project = $this->createProject();
    $project->setUnpublished();
    $project->save();
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
   * Tests the project notify resource - no permission.
   *
   * @covers ::access
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
