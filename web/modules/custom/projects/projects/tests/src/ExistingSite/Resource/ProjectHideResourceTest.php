<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\projects\Event\ProjectHideEvent;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project hide resource.
 */
#[Group('projects')]
class ProjectHideResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the for the project hide resource - standard workflow.
   */
  public function testProjectHide(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setPromoted(TRUE)->save();
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/hide';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project hidden."', $response->getContent());

    // Hiding removes the promotion, but does not archive.
    $stored = $this->reloadProject($project);
    $this->assertTrue($stored->isHidden());
    $this->assertFalse($stored->isPromoted());
    $this->assertFalse($stored->isArchived());
  }

  /**
   * Tests that an unexpected failure is logged and not answered as conflict.
   */
  public function testProjectHideUnexpectedFailure(): void {

    $project = $this->createProject(ProjectState::Open);
    $supervisor = $this->createSupervisor();
    $this->container->get('event_dispatcher')->addListener(ProjectHideEvent::class, static function (): void {
      throw new \LogicException('Exception thrown for testing purposes.');
    }, 2000);

    $path = '/api/projects/' . $project->uuid() . '/hide';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertFalse($this->reloadProject($project)->isHidden());
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
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this action are not met.', $response->getContent());
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
