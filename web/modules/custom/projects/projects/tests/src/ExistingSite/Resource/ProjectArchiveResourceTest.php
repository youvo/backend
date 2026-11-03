<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests for the project archive resource.
 */
#[Group('projects')]
class ProjectArchiveResourceTest extends ProjectResourceTestBase {

  /**
   * Requests the archive resource.
   */
  protected function request(ProjectInterface $project, AccountInterface $account, string $method = 'POST', ?array $content = NULL): Response {
    $request = Request::create('/api/projects/' . $project->uuid() . '/archive', $method, [], [], [], [], $content === NULL ? NULL : Json::encode($content));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $account);
    return $this->doRequest($request);
  }

  /**
   * Tests that supervisors archive open projects once.
   */
  public function testArchive(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->setPromoted(TRUE)->save();
    $supervisor = $this->createSupervisor();

    $content = ['reason' => 'unattractive', 'subject' => 'S', 'body' => 'B'];
    $response = $this->request($project, $supervisor, 'POST', $content);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('"Project archived."', $response->getContent());
    $stored = $this->reloadProject($project);
    $this->assertTrue($stored->isArchived());
    $this->assertFalse($stored->isPromoted());
    $this->assertTrue($stored->lifecycle()->isOpen());

    $response = $this->request($project, $supervisor);
    $this->assertSame(403, $response->getStatusCode());
    $this->assertSame('The project is archived.', $response->getContent());
  }

  /**
   * Tests the reasons and the validation of the request.
   */
  public function testReasonsAndValidation(): void {

    $project = $this->createProject(ProjectState::Completed);
    $supervisor = $this->createSupervisor();

    $response = $this->request($project, $supervisor, 'GET');
    $this->assertSame(200, $response->getStatusCode());
    $reasons = Json::decode((string) $response->getContent())['data']['reasons'];
    $this->assertSame(['custom', 'unattractive', 'external'], array_column($reasons, 'value'));

    $invalid = [
      ['reason' => 'unknown'],
      ['subject' => 'Only a subject'],
      ['subject' => str_repeat('a', 256), 'body' => 'B'],
    ];
    foreach ($invalid as $content) {
      $this->assertSame(400, $this->request($project, $supervisor, 'POST', $content)->getStatusCode());
    }
    $this->assertFalse($this->reloadProject($project)->isArchived());
  }

  /**
   * Tests who may archive in which state.
   */
  public function testAccess(): void {

    $supervisor = $this->createSupervisor();
    foreach ([ProjectState::Draft, ProjectState::Pending, ProjectState::Declined] as $state) {
      $response = $this->request($this->createProject($state), $supervisor);
      $this->assertSame(403, $response->getStatusCode(), $state->value);
    }

    $project = $this->createProject(ProjectState::Draft);
    $this->assertSame(200, $this->request($project, $this->createCreative('administrator'))->getStatusCode());
    $this->assertTrue($this->reloadProject($project)->isArchived());

    $project = $this->createProject(ProjectState::Open);
    $this->assertSame(403, $this->request($project, $project->getOwner()->getManager())->getStatusCode());
    $this->assertSame(403, $this->request($project, $project->getOwner())->getStatusCode());
    $this->assertFalse($this->reloadProject($project)->isArchived());
  }

}
