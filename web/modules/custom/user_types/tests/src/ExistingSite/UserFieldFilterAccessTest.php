<?php

namespace Drupal\Tests\user_types\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests that JSON:API filters and sorts respect hidden user fields.
 *
 * Otherwise, a filter reveals a hidden value character by character.
 */
#[Group('user_types')]
class UserFieldFilterAccessTest extends ProjectResourceTestBase {

  /**
   * Only managers filter and sort by the phone of creatives.
   */
  public function testCreativePhone(): void {

    $phone = '0' . random_int(100000000, 999999999);
    $creative = $this->createCreative();
    $creative->set('field_phone', $phone)->save();
    $project = $this->createProject(ProjectState::Ongoing);
    $project->appendParticipant($creative)->save();
    $filter = '?filter[phone]=' . $phone;
    $participant_filter = '?filter[participants.phone]=' . $phone;

    $this->assertSame(401, $this->get('/api/users' . $filter, NULL)->getStatusCode());
    $this->assertSame(401, $this->get('/api/projects' . $participant_filter, NULL)->getStatusCode());
    $this->assertSame(401, $this->get('/api/projects?sort=participants.phone', NULL)->getStatusCode());

    $other = $this->createCreative();
    $this->assertSame(403, $this->get('/api/users?filter[phone]=' . $phone, $other)->getStatusCode());
    $this->assertSame(403, $this->get('/api/projects' . $participant_filter, $other)->getStatusCode());

    $manager = $this->createManager();
    $this->assertSame([$creative->uuid()], $this->ids($this->get('/api/users?filter[phone]=' . $phone, $manager)));
    // The participants themselves are only filterable for supervisors.
    $supervisor = $this->createSupervisor();
    $this->assertSame([$project->uuid()], $this->ids($this->get('/api/projects' . $participant_filter, $supervisor)));

    // Public fields stay filterable for everyone.
    $response = $this->get('/api/users?filter[id]=' . $creative->uuid() . '&sort=name', NULL);
    $this->assertSame([$creative->uuid()], $this->ids($response));
  }

  /**
   * Only supervisors filter by the phone of organizations.
   */
  public function testOrganizationPhone(): void {

    $phone = '0' . random_int(100000000, 999999999);
    $organization = $this->createOrganization();
    $organization->set('field_phone', $phone)->save();
    $path = '/api/organizations?filter[phone]=' . $phone;

    $this->assertSame(401, $this->get($path, NULL)->getStatusCode());
    $this->assertSame(403, $this->get($path, $this->createOrganization())->getStatusCode());
    $this->assertSame(403, $this->get($path, $this->createManager())->getStatusCode());
    $this->assertSame([$organization->uuid()], $this->ids($this->get($path, $this->createSupervisor())));
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
