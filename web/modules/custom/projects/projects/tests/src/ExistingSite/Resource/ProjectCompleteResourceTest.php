<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\Component\Serialization\Json;
use Drupal\file\Entity\File;
use Drupal\projects\ProjectState;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project complete resource.
 *
 * @coversDefaultClass \Drupal\projects\Plugin\rest\resource\ProjectCompleteResource
 * @group projects
 */
class ProjectCompleteResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the project complete resource - standard workflow.
   *
   * @covers ::create
   * @covers ::routes
   * @covers ::access
   * @covers ::post
   * @covers ::preloadFiles
   * @covers ::shapeResults
   */
  public function testProjectComplete(): void {

    $project = $this->createProject(ProjectState::ONGOING);
    $organization = $project->getOwner();
    $participant = $this->createCreative();
    $project->appendParticipant($participant);
    $project->save();

    $file = File::create(['uri' => $this->randomString()]);
    $file->save();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $body = [
      'comment' => $this->randomString(),
      'results' => [
        [
          'type' => 'file',
          'value' => $file->uuid(),
          'description' => $this->randomString(),
        ],
        [
          'type' => 'file',
          'value' => 'a73b8b10-061c-4f0a-938d-118fcedba242',
          'description' => 'File not found',
        ],
        [
          'type' => 'link',
          'value' => $this->randomString(),
          'description' => $this->randomString(),
        ],
      ],
    ];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project completed."', $response->getContent());
  }

  /**
   * Tests the project complete resource - manager.
   *
   * @covers ::access
   */
  public function testProjectCompleteManager(): void {

    $project = $this->createProject(ProjectState::ONGOING);
    $manager = $project->getOwner()->getManager();
    $participant = $this->createCreative();
    $project->appendParticipant($participant);
    $project->save();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project completed."', $response->getContent());
  }

  /**
   * Tests the project complete resource - participant.
   *
   * @covers ::access
   */
  public function testProjectCompleteParticipant(): void {

    $project = $this->createProject(ProjectState::ONGOING);
    $participant = $this->createCreative();
    $project->appendParticipant($participant);
    $project->save();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $participant);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project completed."', $response->getContent());
  }

  /**
   * Tests the project complete resource - supervisor.
   *
   * @covers ::access
   */
  public function testProjectCompleteSupervisor(): void {

    $project = $this->createProject(ProjectState::ONGOING);
    $supervisor = $this->createSupervisor();
    $participant = $this->createCreative();
    $project->appendParticipant($participant);
    $project->save();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $supervisor);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project completed."', $response->getContent());
  }

  /**
   * Tests the project complete resource - not ongoing.
   *
   * @covers ::post
   */
  public function testProjectCompleteNotOngoing(): void {

    $project = $this->createProject(ProjectState::OPEN);
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals('Project can not be completed.', $response->getContent());
  }

  /**
   * Tests the project complete resource - not owner.
   *
   * @covers ::access
   */
  public function testProjectCompleteNotOwner(): void {

    $project = $this->createProject(ProjectState::ONGOING);
    $other_organization = $this->createOrganization();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());
  }

  /**
   * Tests the project complete resource - not manager.
   *
   * @covers ::access
   */
  public function testProjectCompleteNotManager(): void {

    $project = $this->createProject(ProjectState::ONGOING);
    $other_manager = $this->createManager();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_manager);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());
  }

  /**
   * Tests the project complete resource - not participant.
   *
   * @covers ::access
   */
  public function testProjectCompleteNotParticipant(): void {

    $project = $this->createProject(ProjectState::ONGOING);
    $other_participant = $this->createCreative();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $other_participant);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());
  }

  /**
   * Tests the project complete resource - not published (status).
   *
   * @covers ::access
   */
  public function testProjectCompleteNotPublished(): void {

    $project = $this->createProject();
    $project->setUnpublished();
    $project->save();
    $organization = $project->getOwner();

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The project conditions for this transition are not met.', $response->getContent());
  }

  /**
   * Tests the project complete resource - no permission.
   *
   * @covers ::access
   */
  public function testProjectCompleteNoPermission(): void {

    $project = $this->createProject();
    $editor = $this->createCreative('editor');

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $request = Request::create($path, 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $editor);

    $response = $this->doRequest($request);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('The \'restful post project:complete\' permission is required.', $response->getContent());
  }

}
