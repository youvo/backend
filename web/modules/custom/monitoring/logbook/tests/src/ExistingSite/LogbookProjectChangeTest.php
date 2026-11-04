<?php

namespace Drupal\Tests\logbook\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\file\Entity\File;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\MailInterceptTrait;
use Drupal\Tests\youvo\Traits\RequestTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the log of content changes of projects.
 *
 * Doesn't extend ProjectResourceTestBase, which removes the logbook listeners.
 */
#[Group('logbook')]
class LogbookProjectChangeTest extends ExistingSiteBase {

  use MailInterceptTrait;
  use RequestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->interceptMails();
  }

  /**
   * Creates a project of an organization with a manager.
   */
  protected function createProject(ProjectState $state): ProjectInterface {

    $manager = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);

    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.org',
      'field_manager' => $manager,
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);

    $project = Project::create([
      'uid' => $organization->id(),
      'title' => $this->randomMachineName(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());

    return $project;
  }

  /**
   * Gets the project change logs of a project.
   *
   * @return \Drupal\logbook\LogInterface[]
   *   The logs.
   */
  protected function changeLogs(ProjectInterface $project): array {
    $ids = $this->container->get('entity_type.manager')->getStorage('log')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'project_change')
      ->condition('project', $project->id())
      ->execute();
    $logs = Log::loadMultiple($ids);
    array_walk($logs, fn (LogInterface $log) => $this->markEntityForCleanup($log));
    return array_values($logs);
  }

  /**
   * Sends a JSON:API request for a project as the given account.
   */
  protected function jsonApi(string $method, ProjectInterface $project, AccountInterface $account, array $data = []): Response {
    $content = $data ? Json::encode(['data' => $data]) : NULL;
    $request = Request::create('/api/projects/' . $project->uuid(), $method, [], [], [], [], $content);
    $request->headers->set('Content-Type', 'application/vnd.api+json');
    $request->headers->set('Accept', 'application/vnd.api+json');
    $this->authenticateRequest($request, $account);
    // Requests are handled in this process: reset the static access caches.
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();
    return $this->doRequest($request);
  }

  /**
   * Fills every content field of a project, as the hub would.
   */
  protected function fillContent(ProjectInterface $project): void {
    $source = \Drupal::service('file_system')->copy('core/misc/druplicon.png', 'public://', 1);
    $file = File::create(['uri' => $source, 'status' => 1]);
    $file->save();
    $this->markEntityForCleanup($file);
    $project->set('body', ['value' => '<p>Details</p>', 'format' => 'plain_text'])
      ->set('field_allowance', TRUE)
      ->set('field_appreciation', 'Thanks')
      ->set('field_city', 'Hamburg')
      ->set('field_local', TRUE)
      ->set('field_deadline', '2030-01-31')
      ->set('field_image', ['target_id' => $file->id(), 'alt' => 'Alt'])
      ->set('field_image_copyright', 'Someone')
      ->set('field_material', 'Material')
      ->set('field_workload', '2.5')
      ->setSyncing(TRUE)
      ->save();
    $project->setSyncing(FALSE);
  }

  /**
   * Tests that saving unchanged content through JSON:API doesn't log.
   */
  public function testUnchangedPatchDoesNotLog(): void {

    $project = $this->createProject(ProjectState::Pending);
    $this->fillContent($project);
    $organization = $project->getOwner();
    $revision_id = (int) $project->getRevisionId();

    $response = $this->jsonApi('GET', $project, $organization);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    // Responses are flattened; requests use the regular JSON:API structure.
    $resource = Json::decode((string) $response->getContent())['data'];
    $names = [
      'title',
      'body',
      'allowance',
      'appreciation',
      'city',
      'local',
      'deadline',
      'image_copyright',
      'material',
      'workload',
    ];
    $attributes = array_intersect_key($resource, array_flip($names));
    unset($attributes['body']['processed']);
    $data = [
      'type' => $resource['type'],
      'id' => $resource['id'],
      'attributes' => $attributes,
      'relationships' => ['image' => ['data' => $resource['image']]],
    ];

    $response = $this->jsonApi('PATCH', $project, $organization, $data);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $reloaded = $this->container->get('entity_type.manager')->getStorage('project')->loadUnchanged($project->id());
    $this->assertSame($revision_id, (int) $reloaded->getRevisionId());
    $this->assertSame([], $this->changeLogs($project));
  }

  /**
   * Tests that unchanged content without derived values doesn't log.
   *
   * Clients may leave out the image dimensions, which the image field fills
   * on save, and the empty summary of the body.
   */
  public function testUnchangedPartialPatchDoesNotLog(): void {

    $project = $this->createProject(ProjectState::Pending);
    $this->fillContent($project);
    $organization = $project->getOwner();
    $revision_id = (int) $project->getRevisionId();
    $image = $project->get('field_image')->entity;

    $response = $this->jsonApi('PATCH', $project, $organization, [
      'type' => 'project',
      'id' => $project->uuid(),
      'attributes' => [
        'body' => ['value' => '<p>Details</p>', 'format' => 'plain_text'],
        'workload' => 2.5,
      ],
      'relationships' => [
        'image' => ['data' => ['type' => 'file', 'id' => $image->uuid(), 'meta' => ['alt' => 'Alt']]],
      ],
    ]);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $reloaded = $this->container->get('entity_type.manager')->getStorage('project')->loadUnchanged($project->id());
    $this->assertSame($revision_id, (int) $reloaded->getRevisionId());
    $this->assertSame([], $this->changeLogs($project));
  }

  /**
   * Tests that the organization editing via JSON:API writes a log.
   */
  public function testPatchLogsWithOrganizationAsAuthor(): void {

    $project = $this->createProject(ProjectState::Pending);
    $organization = $project->getOwner();

    $response = $this->jsonApi('PATCH', $project, $organization, [
      'type' => 'project',
      'id' => $project->uuid(),
      'attributes' => ['title' => 'Edited'],
    ]);
    $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());

    $logs = $this->changeLogs($project);
    $this->assertCount(1, $logs);
    $this->assertSame((int) $organization->id(), (int) $logs[0]->getOwnerId());
    $this->assertSame(['title'], $logs[0]->getMisc()['fields']);
  }

  /**
   * Tests that a content change after submit writes a log.
   */
  public function testEditAfterSubmitLogs(): void {

    $project = $this->createProject(ProjectState::Pending);
    $organization = $project->getOwner();
    $previous_revision_id = (int) $project->getRevisionId();
    $this->setCurrentUser($organization);

    $project->setTitle('Changed')->set('field_city', 'Hamburg')->save();

    $logs = $this->changeLogs($project);
    $this->assertCount(1, $logs);
    $log = $logs[0];
    $this->assertSame((int) $organization->id(), (int) $log->getOwnerId());
    $this->assertSame((int) $organization->id(), (int) $log->getOrganization()?->id());
    $this->assertSame((int) $organization->getManager()->id(), (int) $log->getManager()?->id());
    $this->assertSame([
      'fields' => ['title', 'field_city'],
      'previous_revision_id' => $previous_revision_id,
      'revision_id' => (int) $project->getRevisionId(),
    ], $log->getMisc());

    $markup = (string) $log->get('markup')->value;
    $this->assertStringContainsString('bearbeitet: Project Title, City.', $markup);
    $this->assertStringContainsString('/projects/' . $project->id() . '/revisions/' . $project->getRevisionId() . '/view', $markup);

    $processed = (string) $log->get('processed')->value;
    $this->assertStringNotContainsString('%Changes', $processed);
    $this->assertStringNotContainsString('%Diff', $processed);
    $this->assertStringNotContainsString('/revisions/', $processed);
  }

  /**
   * Tests that a manager's content change logs with the manager as author.
   */
  public function testManagerEditLogs(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();
    $this->setCurrentUser($manager);

    $project->setTitle('Changed')->save();

    $logs = $this->changeLogs($project);
    $this->assertCount(1, $logs);
    $this->assertSame((int) $manager->id(), (int) $logs[0]->getOwnerId());
    $this->assertStringContainsString('von der Managerin', (string) $logs[0]->get('markup')->value);
  }

  /**
   * Tests that a project owned by an administrator saves without a log.
   */
  public function testAdministratorOwnedProjectSavesWithoutLog(): void {

    $administrator = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $administrator->addRole('administrator');
    $administrator->save();
    $this->markEntityForCleanup($administrator);
    // Administrators may own projects, for example after changing the owner
    // in the admin form.
    $project = $this->createProject(ProjectState::Pending);
    $project->setOwnerId($administrator->id())->setTitle('Changed')->save();

    $this->assertSame('Changed', $this->container->get('entity_type.manager')->getStorage('project')->loadUnchanged($project->id())->getTitle());
    $this->assertSame([], $this->changeLogs($project));
  }

  /**
   * Tests that draft edits and workflow saves don't log.
   */
  public function testDraftAndWorkflowSavesDontLog(): void {

    $draft = $this->createProject(ProjectState::Draft);
    $draft->setTitle('Changed')->save();
    $this->assertSame([], $this->changeLogs($draft));

    $pending = $this->createProject(ProjectState::Pending);
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $creative->addRole('creative');
    $creative->save();
    $this->markEntityForCleanup($creative);
    $pending->appendApplicant($creative)->save();
    $this->assertSame([], $this->changeLogs($pending));
  }

}
