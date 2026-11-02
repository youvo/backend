<?php

namespace Drupal\Tests\projects\ExistingSite\Resource;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountInterface;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\Plugin\rest\resource\Transition\ProjectDeclineResource;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests the project decline resource.
 */
#[CoversClass(ProjectDeclineResource::class)]
#[Group('projects')]
class ProjectDeclineResourceTest extends ProjectResourceTestBase {

  /**
   * Requests the decline resource.
   */
  protected function request(ProjectInterface $project, AccountInterface $account, string $method = 'POST', ?array $content = NULL): Response {
    $body = $content === NULL ? NULL : Json::encode($content);
    $request = Request::create('/api/projects/' . $project->uuid() . '/decline', $method, [], [], [], [], $body);
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $account);
    return $this->doRequest($request);
  }

  /**
   * Reloads the project from storage.
   */
  protected function reload(ProjectInterface $project): ProjectInterface {
    /** @var \Drupal\projects\ProjectInterface $reloaded */
    $reloaded = $this->container->get('entity_type.manager')->getStorage('project')->loadUnchanged($project->id());
    return $reloaded;
  }

  /**
   * Tests that the manager gets the decline reasons.
   */
  public function testGetReasons(): void {

    $project = $this->createProject(ProjectState::Pending);
    $response = $this->request($project, $project->getOwner()->getManager(), 'GET');

    $this->assertSame(200, $response->getStatusCode());
    $data = Json::decode((string) $response->getContent())['data'];
    $this->assertSame($project->uuid(), $data['id']);
    $this->assertContains(['value' => 'deadline', 'label' => 'Deadline too short'], $data['reasons']);
  }

  /**
   * The captured decline events.
   *
   * @var \Drupal\projects\Event\ProjectDeclineEvent[]
   */
  protected array $events = [];

  /**
   * Captures the decline events.
   *
   * The test base keeps only the project listeners, so the mailer does not
   * send: the event carries the mail instead.
   */
  protected function captureEvents(): void {
    $this->container->get('event_dispatcher')->addListener(ProjectDeclineEvent::class, function (ProjectDeclineEvent $event): void {
      $this->events[] = $event;
    });
  }

  /**
   * Tests that the manager declines with an edited mail.
   */
  public function testDeclineWithMail(): void {

    $project = $this->createProject(ProjectState::Pending);
    $manager = $project->getOwner()->getManager();
    $this->captureEvents();
    $response = $this->request($project, $manager, 'POST', [
      'reason' => 'deadline',
      'subject' => 'Your project',
      'body' => 'Sorry.',
    ]);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($this->reload($project)->lifecycle()->isDeclined());
    $this->assertCount(1, $this->events);
    $this->assertSame(ProjectDeclineReason::Deadline, $this->events[0]->getReason());
    $this->assertSame('Your project', $this->events[0]->getSubject());
    $this->assertSame('Sorry.', $this->events[0]->getBody());
    $this->assertSame((int) $manager->id(), (int) $this->events[0]->getSender()->id());
  }

  /**
   * Tests that a decline without subject and body carries no mail.
   */
  public function testDeclineWithoutMail(): void {

    $project = $this->createProject(ProjectState::Pending);
    $this->captureEvents();
    $response = $this->request($project, $project->getOwner()->getManager(), 'POST', []);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($this->reload($project)->lifecycle()->isDeclined());
    $this->assertSame(ProjectDeclineReason::Custom, $this->events[0]->getReason());
    $this->assertNull($this->events[0]->getSubject());
    $this->assertNull($this->events[0]->getSender());
  }

  /**
   * Tests the invalid request bodies.
   */
  public function testBadRequests(): void {
    $project = $this->createProject(ProjectState::Pending);
    $manager = $project->getOwner()->getManager();
    $cases = [
      'unknown reason' => ['reason' => 'nope'],
      'subject only' => ['subject' => 'S'],
      'empty body' => ['subject' => 'S', 'body' => ' '],
      'long subject' => ['subject' => str_repeat('s', 256), 'body' => 'B'],
    ];
    foreach ($cases as $label => $content) {
      $this->assertSame(400, $this->request($project, $manager, 'POST', $content)->getStatusCode(), $label);
    }
    $this->assertTrue($this->reload($project)->lifecycle()->isPending());
  }

  /**
   * Tests that an open project cannot be declined.
   */
  public function testDeclineOpenProject(): void {

    $project = $this->createProject(ProjectState::Open);
    $response = $this->request($project, $project->getOwner()->getManager(), 'POST', []);

    $this->assertSame(409, $response->getStatusCode());
    $this->assertSame('Project can not be declined.', $response->getContent());
  }

  /**
   * Tests the access per role.
   */
  public function testAccess(): void {

    $project = $this->createProject(ProjectState::Pending);
    foreach ([$this->createManager(), $this->createCreative(), $project->getOwner()] as $account) {
      $this->assertSame(403, $this->request($project, $account, 'POST', [])->getStatusCode());
    }
    $this->assertTrue($this->reload($project)->lifecycle()->isPending());

    // Supervisors decline any project.
    $this->assertSame(200, $this->request($project, $this->createSupervisor(), 'POST', [])->getStatusCode());
    $this->assertTrue($this->reload($project)->lifecycle()->isDeclined());
  }

}
