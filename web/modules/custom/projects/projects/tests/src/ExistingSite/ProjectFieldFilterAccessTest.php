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
   * Creatives filter by their own applications and participations only.
   *
   * The paths are those of the dashboard of the creatives app.
   */
  public function testOwnApplicationsAndParticipations(): void {

    $creative = $this->createCreative();
    $other = $this->createCreative();
    $applied = $this->createProject(ProjectState::Open);
    $applied->appendApplicant($creative)->appendApplicant($other)->save();
    $other_applied = $this->createProject(ProjectState::Open);
    $other_applied->appendApplicant($other)->save();
    $participating = $this->createProject(ProjectState::Ongoing);
    $participating->appendParticipant($creative)->save();
    $other_participating = $this->createProject(ProjectState::Ongoing);
    $other_participating->appendParticipant($other)->save();
    $created = [
      $applied->uuid(),
      $other_applied->uuid(),
      $participating->uuid(),
      $other_participating->uuid(),
    ];

    $dashboard = fn (string $name, string $path, string $uuid, string $lifecycle) => '/api/projects?' . http_build_query([
      'include' => 'image,organization',
      'filter' => [
        $name => ['condition' => ['path' => $path, 'value' => $uuid]],
        'lifecycle' => ['condition' => ['path' => 'lifecycle', 'value' => $lifecycle]],
      ],
      'sort' => ['creation' => ['path' => 'created', 'direction' => 'ASC']],
    ]);
    $applications = $dashboard('applicant', 'applicants.id', $creative->uuid(), 'open');
    $participations = $dashboard('participant', 'participants.id', $creative->uuid(), 'ongoing');

    // The creative gets their own projects and none of the other creative.
    $applied_ids = array_intersect($this->ids($this->get($applications, $creative)), $created);
    $this->assertSame([$applied->uuid()], array_values($applied_ids));
    $participating_ids = array_intersect($this->ids($this->get($participations, $creative)), $created);
    $this->assertSame([$participating->uuid()], array_values($participating_ids));

    // The same request of another creative is refused, also when the
    // response of the first one is cached.
    $this->assertSame(403, $this->get($applications, $other)->getStatusCode());
    $this->assertSame(403, $this->get($participations, $other)->getStatusCode());

    // Other operators, paths, sorts and resources are refused.
    $refused = [
      '/api/projects?filter[applicants.id]=' . $other->uuid(),
      '/api/projects?filter[a][condition][path]=applicants.id&filter[a][condition][operator]=<>&filter[a][condition][value]=' . $creative->uuid(),
      '/api/projects?filter[a][condition][path]=applicants.id&filter[a][condition][operator]=IN&filter[a][condition][value][]=' . $creative->uuid() . '&filter[a][condition][value][]=' . $other->uuid(),
      '/api/projects?filter[a][condition][path]=participants.id&filter[a][condition][operator]=IS%20NULL',
      '/api/projects?filter[participants.meta.drupal_internal__target_id]=' . $other->id(),
      '/api/projects?filter[participants.name]=' . rawurlencode($other->getAccountName()),
      '/api/projects?filter[participants.id]=' . $creative->uuid() . '&sort=applicants.id',
      '/api/results?filter[project.participants.id]=' . $creative->uuid(),
    ];
    foreach ($refused as $path) {
      $this->assertSame(403, $this->get($path, $creative)->getStatusCode(), $path);
    }
  }

  /**
   * Organizations do not filter by applicants or participants, not even own.
   */
  public function testOrganizations(): void {

    $organization = $this->createOrganization();
    $this->assertSame(403, $this->get('/api/projects?filter[applicants.id]=' . $organization->uuid(), $organization)->getStatusCode());
    $this->assertSame(403, $this->get('/api/projects?filter[participants.id]=' . $organization->uuid(), $organization)->getStatusCode());
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
