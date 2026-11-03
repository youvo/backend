<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests that JSON:API filters respect hidden project fields.
 *
 * Otherwise, a filter on the applicants reveals where a creative applied.
 */
#[Group('projects')]
class ProjectFieldFilterAccessTest extends ProjectResourceTestBase {

  /**
   * Only project administrators filter by applicants and participants.
   */
  public function testApplicantsAndParticipants(): void {

    $creative = $this->createCreative();
    $open = $this->createProject(ProjectState::Open);
    $open->appendApplicant($creative)->save();
    $ongoing = $this->createProject(ProjectState::Ongoing);
    $ongoing->appendParticipant($creative)->save();
    $applicant_filter = '/api/projects?filter[applicants.id]=' . $creative->uuid();
    $participant_filter = '/api/projects?filter[participants.id]=' . $creative->uuid();

    $this->assertSame(401, $this->get($applicant_filter, NULL)->getStatusCode());
    $this->assertSame(401, $this->get($participant_filter, NULL)->getStatusCode());
    $this->assertSame(403, $this->get($applicant_filter, $this->createCreative())->getStatusCode());
    $this->assertSame(403, $this->get($participant_filter, $this->createManager())->getStatusCode());

    $supervisor = $this->createSupervisor();
    $this->assertSame([$open->uuid()], $this->ids($this->get($applicant_filter, $supervisor)));
    $this->assertSame([$ongoing->uuid()], $this->ids($this->get($participant_filter, $supervisor)));
  }

  /**
   * Public fields stay filterable, fields for logged-in users only for them.
   */
  public function testPublicAndAuthenticatedFields(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->set('field_contact', 'Contact ' . $this->randomMachineName())->save();
    $id_filter = '/api/projects?filter[id]=' . $project->uuid();
    $contact_filter = $id_filter . '&filter[contact]=' . $project->get('field_contact')->value;

    $this->assertSame([$project->uuid()], $this->ids($this->get($id_filter . '&filter[lifecycle]=open', NULL)));
    $this->assertSame(401, $this->get($contact_filter, NULL)->getStatusCode());
    $this->assertSame([$project->uuid()], $this->ids($this->get($contact_filter, $this->createCreative())));
  }

  /**
   * Sends a JSON:API GET request, anonymous without an account.
   */
  protected function get(string $path, ?AccountInterface $account): Response {
    $request = Request::create($path);
    $request->headers->set('Accept', 'application/vnd.api+json');
    if ($account) {
      $this->authenticateRequest($request, $account);
    }
    return $this->doRequest($request);
  }

  /**
   * Gets the IDs of the resources in a successful response.
   */
  protected function ids(Response $response): array {
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    return array_column(Json::decode((string) $response->getContent())['data'], 'id');
  }

}
