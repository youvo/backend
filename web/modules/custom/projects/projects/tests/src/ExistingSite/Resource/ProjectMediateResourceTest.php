<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\Component\Serialization\Json;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project mediate resource.
 */
#[Group('projects')]
class ProjectMediateResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the project mediate resource - standard GET workflow.
   */
  public function testProjectMediateGet(): void {

    $project = $this->createProject(ProjectState::Open);
    $organization = $project->getOwner();
    $applicant = $this->createCreative();
    $project->appendApplicant($applicant);
    $project->save();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'GET');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString($project->uuid(), $response->getContent());
    $this->assertStringContainsString($applicant->uuid(), $response->getContent());
  }

  /**
   * Tests the project mediate resource - standard POST workflow.
   */
  public function testProjectMediate(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setPromoted(TRUE)->save();
    $organization = $project->getOwner();
    $manager = $organization->getManager();
    $selected = [$this->createCreative(), $this->createCreative()];

    // A selected manager participates once, as manager.
    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $body = [
      'selected_creatives' => [
        $selected[0]->uuid(),
        $selected[1]->uuid(),
        $manager->uuid(),
      ],
    ];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project mediated."', $response->getContent());

    $stored = $this->reloadProject($project);
    $this->assertLastTransition($stored, ProjectTransition::Mediate, ProjectState::Open, ProjectState::Ongoing);
    $this->assertFalse($stored->isPromoted());
    $participants = array_column($stored->get('field_participants')->getValue(), 'target_id');
    $tasks = array_column($stored->get('field_participants_tasks')->getValue(), 'value');
    $expected = [
      $selected[0]->id() => 'Creative',
      $selected[1]->id() => 'Creative',
      $manager->id() => 'Manager',
    ];
    $actual = array_combine($participants, $tasks);
    ksort($expected);
    ksort($actual);
    $this->assertCount(3, $tasks);
    $this->assertEquals($expected, $actual);
  }

  /**
   * Tests the project mediate resource - malformed bodies answer 400.
   */
  public function testProjectMediateMalformed(): void {
    $project = $this->createProject(ProjectState::Open);
    foreach (['"x"', '{"selected_creatives":"x"}', '{"selected_creatives":[["x"]]}'] as $body) {
      $request = Request::create('/api/projects/' . $project->uuid() . '/mediate', 'POST', [], [], [], [], $body);
      $request->headers->set('Content-Type', 'application/json');
      $this->authenticateRequest($request, $project->getOwner());
      $this->assertEquals(400, $this->doRequest($request)->getStatusCode(), $body);
    }
    $this->assertTrue($this->reloadProject($project)->lifecycle()->isOpen());
  }

  /**
   * Tests the project mediate resource - manager.
   */
  public function testProjectMediateManager(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $body = ['selected_creatives' => [$this->createCreative()->uuid()]];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project mediated."', $response->getContent());
    $this->assertTrue($this->reloadProject($project)->lifecycle()->isOngoing());
  }

  /**
   * Tests the project mediate resource - supervisor.
   */
  public function testProjectMediateSupervisor(): void {

    $project = $this->createProject(ProjectState::Open);
    $supervisor = $this->createSupervisor();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $body = ['selected_creatives' => [$this->createCreative()->uuid()]];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project mediated."', $response->getContent());
    $this->assertTrue($this->reloadProject($project)->lifecycle()->isOngoing());
  }

  /**
   * Tests the project mediate resource - not open.
   */
  public function testProjectMediateNotOpen(): void {

    $project = $this->createProject(ProjectState::Completed);
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $body = ['selected_creatives' => [$this->createCreative()->uuid()]];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('Project can not be mediated.', $response->getContent());
  }

  /**
   * Tests the project mediate resource - not owner.
   */
  public function testProjectMediateNotOwner(): void {

    $project = $this->createProject(ProjectState::Open);
    $other_organization = $this->createOrganization();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());
  }

  /**
   * Tests the project mediate resource - not manager.
   */
  public function testProjectMediateNotManager(): void {

    $project = $this->createProject(ProjectState::Open);
    $other_manager = $this->createManager();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());
  }

  /**
   * Tests the project mediate resource - no permission.
   */
  public function testProjectMediateNoPermission(): void {

    $project = $this->createProject();
    $creative = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/mediate';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:mediate\' permission is required.', $response->getContent());
  }

}
