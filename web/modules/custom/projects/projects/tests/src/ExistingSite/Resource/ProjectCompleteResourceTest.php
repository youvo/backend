<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\Component\Serialization\Json;
use Drupal\file\Entity\File;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests for the project complete resource.
 */
#[Group('projects')]
class ProjectCompleteResourceTest extends ProjectResourceTestBase {

  /**
   * Tests the project complete resource - standard workflow.
   */
  public function testProjectComplete(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $organization = $project->getOwner();
    $participant = $this->createCreative();
    $project->appendParticipant($participant);
    $project->save();

    $file = File::create(['uri' => 'public://' . $this->randomMachineName() . '.txt']);
    $file->save();
    $this->markEntityForCleanup($file);

    $path = '/api/projects/' . $project->uuid() . '/complete';
    $body = [
      'comment' => $this->randomString(),
      'results' => [
        [
          'type' => 'file',
          'value' => $file->uuid(),
          'description' => 'File description',
        ],
        [
          'type' => 'file',
          'value' => 'a73b8b10-061c-4f0a-938d-118fcedba242',
          'description' => 'File not found',
        ],
        [
          'type' => 'link',
          'value' => 'https://example.com',
          'description' => 'Link description',
        ],
      ],
    ];
    $request = Request::create($path, 'POST', [], [], [], [], Json::encode($body));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $organization);

    $response = $this->doRequest($request);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('"Project completed."', $response->getContent());

    // The project is completed with its results, the unknown file is skipped.
    $stored = $this->reloadProject($project);
    $this->assertLastTransition($stored, ProjectTransition::Complete, ProjectState::Ongoing, ProjectState::Completed);
    /** @var \Drupal\projects\ProjectResultInterface $result */
    $result = $this->container->get('entity_type.manager')->getStorage('project_result')->loadUnchanged($stored->getResult()->id());
    $files = $result->get('field_files')->getValue();
    $this->assertCount(1, $files);
    $this->assertEquals($file->id(), $files[0]['target_id']);
    $this->assertSame('File description', $files[0]['description']);
    $links = $result->get('field_hyperlinks')->getValue();
    $this->assertCount(1, $links);
    $this->assertSame('https://example.com', $links[0]['value']);
    $this->assertSame('Link description', $links[0]['description']);
  }

  /**
   * Tests the project complete resource - malformed bodies answer 400.
   */
  public function testProjectCompleteMalformed(): void {
    $project = $this->createProject(ProjectState::Ongoing);
    $project->appendParticipant($this->createCreative());
    $project->save();
    foreach (['"x"', '{"results":"x"}', '{"results":["x"]}'] as $body) {
      $request = Request::create('/api/projects/' . $project->uuid() . '/complete', 'POST', [], [], [], [], $body);
      $request->headers->set('Content-Type', 'application/json');
      $this->authenticateRequest($request, $project->getOwner());
      $this->assertEquals(400, $this->doRequest($request)->getStatusCode(), $body);
    }
    $this->assertTrue($this->reloadProject($project)->lifecycle()->isOngoing());
  }

  /**
   * Tests the project complete resource - manager.
   */
  public function testProjectCompleteManager(): void {

    $project = $this->createProject(ProjectState::Ongoing);
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
    $this->assertTrue($this->reloadProject($project)->lifecycle()->isCompleted());
  }

  /**
   * Tests the project complete resource - participant.
   */
  public function testProjectCompleteParticipant(): void {

    $project = $this->createProject(ProjectState::Ongoing);
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
    $this->assertTrue($this->reloadProject($project)->lifecycle()->isCompleted());
  }

  /**
   * Tests the project complete resource - supervisor.
   */
  public function testProjectCompleteSupervisor(): void {

    $project = $this->createProject(ProjectState::Ongoing);
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
    $this->assertTrue($this->reloadProject($project)->lifecycle()->isCompleted());
  }

  /**
   * Tests the project complete resource - not ongoing.
   */
  public function testProjectCompleteNotOngoing(): void {

    $project = $this->createProject(ProjectState::Open);
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
   */
  public function testProjectCompleteNotOwner(): void {

    $project = $this->createProject(ProjectState::Ongoing);
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
   */
  public function testProjectCompleteNotManager(): void {

    $project = $this->createProject(ProjectState::Ongoing);
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
   */
  public function testProjectCompleteNotParticipant(): void {

    $project = $this->createProject(ProjectState::Ongoing);
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
   * Tests the project complete resource - no permission.
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
