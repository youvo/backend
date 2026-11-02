<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the archive and hidden flags and who may edit the action fields.
 */
#[Group('projects')]
class ProjectActionFieldsTest extends ProjectResourceTestBase {

  /**
   * The values to PATCH per action field, by public name.
   */
  protected const array ACTION_VALUES = [
    'status' => FALSE,
    'promote' => TRUE,
    'hidden' => TRUE,
    'lifecycle' => 'pending',
  ];

  /**
   * Patches attributes of a project via JSON:API.
   */
  protected function patch(ProjectInterface $project, AccountInterface $account, array $attributes, array $relationships = []): Response {
    $data = ['type' => 'project', 'id' => $project->uuid(), 'attributes' => $attributes];
    if ($relationships) {
      $data['relationships'] = $relationships;
    }
    $request = Request::create('/api/projects/' . $project->uuid(), 'PATCH', [], [], [], [], Json::encode(['data' => $data]));
    $request->headers->set('Content-Type', 'application/vnd.api+json');
    $request->headers->set('Accept', 'application/vnd.api+json');
    $this->authenticateRequest($request, $account);
    // Requests are handled in this process: reset the static access caches.
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();
    return $this->doRequest($request);
  }

  /**
   * Tests the archive and hidden helpers.
   */
  public function testHelpers(): void {

    $project = $this->createProject(ProjectState::Open);
    $this->assertFalse($project->isArchived());
    $this->assertFalse($project->isHidden());

    $project->setArchived()->setHidden()->save();
    $this->assertTrue($project->isArchived());
    $this->assertFalse($project->isPublished());
    $this->assertTrue($project->isHidden());

    $project->setArchived(FALSE)->setHidden(FALSE)->save();
    $this->assertFalse($project->isArchived());
    $this->assertFalse($project->isHidden());
  }

  /**
   * Tests that supervisors cannot PATCH the action fields, nor the owner.
   *
   * The organization views the hidden flag, but cannot set it either.
   */
  public function testSupervisorCannotPatchActionFields(): void {

    $project = $this->createProject(ProjectState::Open);
    $organization = $project->getOwner();
    $this->assertTrue($project->get('hidden')->access('view', $organization));
    $this->assertFalse($project->get('hidden')->access('edit', $organization));

    $supervisor = $this->createSupervisor();
    foreach (static::ACTION_VALUES as $field => $value) {
      $response = $this->patch($project, $supervisor, [$field => $value]);
      $this->assertSame(403, $response->getStatusCode(), $field);
      $this->assertStringContainsString('not allowed to PATCH the selected field', (string) $response->getContent(), $field);
    }
    $owner = ['organization' => ['data' => ['type' => 'organization', 'id' => $this->createOrganization()->uuid()]]];
    $this->assertSame(403, $this->patch($project, $supervisor, [], $owner)->getStatusCode(), 'organization');

    $stored = $this->reloadProject($project);
    $this->assertFalse($stored->isArchived());
    $this->assertFalse($stored->isPromoted());
    $this->assertFalse($stored->isHidden());
    $this->assertTrue($stored->lifecycle()->isOpen());
    $this->assertSame($project->getOwner()->id(), $stored->getOwner()->id());

    // Other fields stay editable.
    $this->assertSame(200, $this->patch($project, $supervisor, ['title' => 'Edited'])->getStatusCode());
  }

  /**
   * Tests that administrators may PATCH the action fields.
   */
  public function testAdministratorPatchesActionFields(): void {
    $project = $this->createProject(ProjectState::Open);
    $response = $this->patch($project, $this->createCreative('administrator'), ['hidden' => TRUE]);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue(Json::decode((string) $response->getContent())['data']['hidden']);
    $this->assertTrue($this->reloadProject($project)->isHidden());
  }

}
