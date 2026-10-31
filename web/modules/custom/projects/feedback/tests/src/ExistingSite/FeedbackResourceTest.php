<?php

namespace Drupal\Tests\feedback\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\feedback\FeedbackInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\RequestTrait;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the feedback create resource and saving feedback via JSON:API.
 *
 * @coversDefaultClass \Drupal\feedback\Plugin\rest\resource\FeedbackCreateResource
 * @group feedback
 */
class FeedbackResourceTest extends ExistingSiteBase {

  use RequestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    parent::setUp();

    // Only listen to project and feedback events within their modules, e.g. do
    // not write logs to the database.
    $event_dispatcher = $this->container->get('event_dispatcher');
    foreach ($event_dispatcher->getListeners() as $event => $listeners) {
      foreach (['projects', 'feedback'] as $module) {
        if (str_contains($event, $module)) {
          foreach ($listeners as $listener) {
            if (is_array($listener) && !str_contains($listener[0]::class, $module)) {
              $event_dispatcher->removeSubscriber($listener[0]);
            }
          }
        }
      }
    }
  }

  /**
   * Creates a minimal project with a participant for testing purposes.
   */
  protected function createProject(ProjectState $state = ProjectState::Completed): ProjectInterface {

    $manager = $this->createAccount(Creative::class, ['creative', 'manager']);
    $organization = $this->createAccount(Organization::class, ['organization'], ['field_manager' => $manager]);

    $project = Project::create([
      'type' => 'project',
      'uid' => $organization->id(),
      'status' => ProjectInterface::PUBLISHED,
      'title' => $this->randomString(),
      'field_lifecycle' => $state->value,
    ]);
    $project->appendParticipant($this->createAccount(Creative::class, ['creative']));
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());

    return $project;
  }

  /**
   * Creates a user with a unique mail address for basic auth.
   *
   * @param class-string<\Drupal\user\Entity\User> $class
   *   The user bundle class.
   * @param string[] $roles
   *   The roles of the user.
   * @param array $values
   *   Additional values.
   */
  protected function createAccount(string $class, array $roles, array $values = []): UserInterface {
    $user = $class::create($values + [
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    foreach ($roles as $role) {
      $user->addRole($role);
    }
    $user->save();
    $this->markEntityForCleanup($user);
    return $user;
  }

  /**
   * Gets the participant of the project.
   */
  protected function getParticipant(ProjectInterface $project): AccountInterface {
    $participants = $project->getParticipants();
    return reset($participants);
  }

  /**
   * Requests the feedback create resource.
   */
  protected function requestCreate(ProjectInterface $project, AccountInterface $account): Response {
    $request = Request::create('/api/projects/' . $project->uuid() . '/feedback', 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $account);
    return $this->doRequest($request);
  }

  /**
   * Patches a feedback via JSON:API.
   */
  protected function requestPatch(FeedbackInterface $feedback, AccountInterface $account, array $attributes, array $relationships = []): Response {
    $data = [
      'type' => 'feedback--' . $feedback->bundle(),
      'id' => $feedback->uuid(),
      'attributes' => $attributes,
    ];
    if ($relationships) {
      $data['relationships'] = $relationships;
    }
    $path = '/api/feedbacks/' . $feedback->bundle() . '/' . $feedback->uuid();
    $request = Request::create($path, 'PATCH', [], [], [], [], Json::encode(['data' => $data]));
    // Requests are handled in the same process, so the access results of
    // previous requests are still statically cached.
    $this->container->get('entity_type.manager')->getAccessControlHandler('feedback')->resetCache();
    $request->headers->set('Content-Type', 'application/vnd.api+json');
    $request->headers->set('Accept', 'application/vnd.api+json');
    $this->authenticateRequest($request, $account);
    return $this->doRequest($request);
  }

  /**
   * Creates a feedback for the participant via the create resource.
   */
  protected function createFeedback(ProjectInterface $project, AccountInterface $account): FeedbackInterface {
    $response = $this->requestCreate($project, $account);
    $this->assertContains($response->getStatusCode(), [200, 201]);
    $uuid = Json::decode($response->getContent())['data']['id'];
    $feedbacks = $this->container->get('entity_type.manager')
      ->getStorage('feedback')
      ->loadByProperties(['uuid' => $uuid]);
    $feedback = reset($feedbacks);
    $this->assertInstanceOf(FeedbackInterface::class, $feedback);
    $this->markEntityForCleanup($feedback);
    return $feedback;
  }

  /**
   * Reloads the feedback from the database.
   */
  protected function reload(FeedbackInterface $feedback): FeedbackInterface {
    /** @var \Drupal\feedback\FeedbackInterface $reloaded */
    $reloaded = $this->container->get('entity_type.manager')
      ->getStorage('feedback')
      ->loadUnchanged($feedback->id());
    return $reloaded;
  }

  /**
   * Tests that the bundle depends on the role of the account.
   *
   * @covers ::post
   * @covers ::routes
   * @covers \Drupal\feedback\Access\FeedbackCreateAccess::access
   */
  public function testCreateFeedback(): void {

    $project = $this->createProject();

    $response = $this->requestCreate($project, $this->getParticipant($project));
    $this->assertEquals(201, $response->getStatusCode());
    $creative_data = Json::decode($response->getContent())['data'];
    $this->assertEquals('feedback--creative', $creative_data['type']);

    $response = $this->requestCreate($project, $project->getOwner());
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertEquals('feedback--organization', Json::decode($response->getContent())['data']['type']);

    // The existing feedback is returned on a second request.
    $response = $this->requestCreate($project, $this->getParticipant($project));
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals($creative_data['id'], Json::decode($response->getContent())['data']['id']);

    $feedbacks = $this->container->get('entity_type.manager')
      ->getStorage('feedback')
      ->loadByProperties(['project' => $project->id()]);
    $this->assertCount(2, $feedbacks);
    foreach ($feedbacks as $feedback) {
      $this->markEntityForCleanup($feedback);
    }
  }

  /**
   * Tests that completed feedback can not be requested again.
   *
   * @covers ::post
   */
  public function testCreateFeedbackCompleted(): void {

    $project = $this->createProject();
    $participant = $this->getParticipant($project);
    $feedback = $this->createFeedback($project, $participant);
    $feedback->complete()->save();

    $response = $this->requestCreate($project, $participant);
    $this->assertEquals(409, $response->getStatusCode());
  }

  /**
   * Tests the access of the feedback create resource.
   *
   * @covers \Drupal\feedback\Access\FeedbackCreateAccess::access
   */
  public function testCreateFeedbackAccess(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $response = $this->requestCreate($project, $this->getParticipant($project));
    $this->assertEquals(403, $response->getStatusCode());

    $project = $this->createProject();
    $outsider = $this->createAccount(Creative::class, ['creative']);
    $response = $this->requestCreate($project, $outsider);
    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Tests that the author saves answers and completes the feedback.
   *
   * @covers \Drupal\feedback\Entity\Feedback::preSave
   */
  public function testPatchFeedback(): void {

    $project = $this->createProject();
    $participant = $this->getParticipant($project);
    $feedback = $this->createFeedback($project, $participant);

    $response = $this->requestPatch($feedback, $participant, [
      'step' => 1,
      'project_comment' => 'Great project!',
      'satisfied_scale' => 4,
      'recommend_boolean' => TRUE,
    ]);
    $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
    $feedback = $this->reload($feedback);
    $this->assertEquals(4, $feedback->get('field_satisfied_scale')->value);
    $this->assertFalse($feedback->isCompleted());

    $response = $this->requestPatch($feedback, $participant, ['step' => 4]);
    $this->assertEquals(200, $response->getStatusCode(), $response->getContent());
    $this->assertTrue($this->reload($feedback)->isCompleted());

    /** @var \Drupal\projects\ProjectResultInterface $project_result */
    $project_result = $this->container->get('entity_type.manager')
      ->getStorage('project_result')
      ->loadUnchanged($project->getResult()->id());
    $comments = $project_result->getComments();
    $this->assertCount(1, $comments);
    $comment = reset($comments);
    $this->markEntityForCleanup($comment);
    $this->assertEquals($participant->id(), $comment->getOwnerId());
    $this->assertEquals('Great project!', $comment->getComment());

    // The completed feedback can not be changed anymore.
    $response = $this->requestPatch($feedback, $participant, ['step' => 5]);
    $this->assertEquals(403, $response->getStatusCode());
  }

  /**
   * Tests that option answers of the organization are displayed with labels.
   *
   * @covers \Drupal\feedback\Plugin\Field\FieldFormatter\FeedbackOptionsFormatter::viewElements
   */
  public function testOrganizationFeedbackOptionsDisplay(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $feedback = $this->createFeedback($project, $organization);
    $this->assertEquals('organization', $feedback->bundle());

    $response = $this->requestPatch($feedback, $organization, [
      'step' => 2,
      'channels_checkboxes' => [0, 2],
      'result_radios' => 1,
    ]);
    $this->assertEquals(200, $response->getStatusCode(), $response->getContent());

    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('feedback')
      ->view($this->reload($feedback));
    $output = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Telefon', $output);
    $this->assertStringContainsString('Videotelefonie (Skype, Zoom, …)', $output);
    $this->assertStringNotContainsString('E-Mail', $output);
    $this->assertStringContainsString('Nein, wir mussten den Projektumfang verkleinern.', $output);
  }

  /**
   * Tests that the author may not change the fields set by the backend.
   *
   * @covers \Drupal\feedback\Access\FeedbackFieldAccess::checkFieldAccess
   */
  public function testPatchRestrictedFields(): void {

    $project = $this->createProject();
    $participant = $this->getParticipant($project);
    $feedback = $this->createFeedback($project, $participant);

    // Unchanged values of restricted fields are accepted.
    $response = $this->requestPatch($feedback, $participant, ['step' => 1], [
      'project' => ['data' => ['type' => 'project', 'id' => $project->uuid()]],
    ]);
    $this->assertEquals(200, $response->getStatusCode(), $response->getContent());

    $other_project = $this->createProject();
    $response = $this->requestPatch($feedback, $participant, [], [
      'project' => ['data' => ['type' => 'project', 'id' => $other_project->uuid()]],
    ]);
    $this->assertEquals(403, $response->getStatusCode());

    $other_user = $this->createAccount(Creative::class, ['creative']);
    $response = $this->requestPatch($feedback, $participant, [], [
      'author' => ['data' => ['type' => 'user', 'id' => $other_user->uuid()]],
    ]);
    $this->assertEquals(403, $response->getStatusCode());

    $response = $this->requestPatch($feedback, $participant, ['created' => '2000-01-01T00:00:00+00:00']);
    $this->assertEquals(403, $response->getStatusCode());

    $feedback = $this->reload($feedback);
    $this->assertEquals($project->id(), $feedback->getProject()->id());
    $this->assertEquals($participant->id(), $feedback->getOwnerId());
  }

}
