<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\logbook\Entity\LogPattern;
use Drupal\manager\Controller\ContextPaneController;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\ProjectState;
use Drupal\rest\Entity\RestResourceConfig;
use Drupal\rest\RestResourceConfigInterface;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the archive form in the transition pane, driven through the controller.
 *
 * @group manager
 */
class ProjectArchiveFormTest extends MailerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'views',
    'logbook',
    'manager',
    'rest',
    'serialization',
  ];

  /**
   * The session shared by all requests of a test (the CSRF seed lives in it).
   */
  protected ?Session $session = NULL;

  /**
   * The number of dispatched archive events.
   */
  protected int $archiveEvents = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('log');
    $values = Yaml::decode((string) file_get_contents(DRUPAL_ROOT . '/modules/custom/monitoring/logbook/config/install/logbook.log_pattern.project_archive.yml'));
    LogPattern::create(['tokens' => []] + array_diff_key($values, ['tokens' => 1, 'uuid' => 1]))->save();
    // The REST permission exists with the resource config.
    RestResourceConfig::create([
      'id' => 'project.archive',
      'plugin_id' => 'project:archive',
      'granularity' => RestResourceConfigInterface::RESOURCE_GRANULARITY,
      'configuration' => ['methods' => ['GET', 'POST'], 'formats' => ['json'], 'authentication' => ['cookie']],
    ])->save();
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses all permission checks: use it up.
    $this->createMailableCreative('Root');
    $this->container->get('event_dispatcher')->addListener(ProjectArchiveEvent::class, function (): void {
      $this->archiveEvents++;
    }, 2000);
  }

  /**
   * Creates a saved project whose owner and manager have a mail address.
   */
  protected function createArchivableProject(ProjectState $state): Project {
    $project = $this->createProject($state);
    $organization = $project->getOwner();
    $organization->set('mail', 'organization@example.com')->set('field_contact', 'Olga Org')->save();
    $organization->getManager()->set('mail', 'manager@example.com')->set('field_name', 'Manu Manager')->save();
    $project->save();
    $this->assertInstanceOf(Project::class, $project);
    return $project;
  }

  /**
   * Gives an account the permissions of a supervisor who may archive.
   */
  protected function archiver(UserInterface $account): UserInterface {
    return $this->accountWith($account, [
      'administer projects',
      'archive projects',
      'general manager access',
      'restful post project:archive',
      'send project mails',
      'view correspondence',
    ]);
  }

  /**
   * Creates a project and logs in a supervisor.
   */
  protected function actAsSupervisorOf(ProjectState $state): Project {
    $project = $this->createArchivableProject($state);
    $this->container->get('current_user')->setAccount($this->archiver($this->createMailableCreative('Supervisor ' . $this->randomMachineName())));
    return $project;
  }

  /**
   * Runs the pane controller for a request and returns the HTML.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project of the pane URL.
   * @param array<string, string> $query
   *   Query parameters besides the pane type.
   * @param array<string, string>|null $post
   *   POST values, or NULL for a GET request.
   *
   * @return string
   *   The response body.
   */
  protected function pane(Project $project, array $query = [], ?array $post = NULL): string {
    $uri = '/project/manage/context/' . $project->id() . '?' . http_build_query($query + ['type' => 'edit']);
    $request = Request::create($uri, $post === NULL ? 'GET' : 'POST', $post ?? []);
    $request->setSession($this->session ??= new Session(new MockArraySessionStorage()));
    $this->container->set('form_builder', NULL);
    $this->container->set('form_validator', NULL);
    (new \ReflectionProperty(FormState::class, 'anyErrors'))->setValue(NULL, FALSE);
    $stack = $this->container->get('request_stack');
    $stack->push($request);
    try {
      $response = ContextPaneController::create($this->container)->contextPane($project, $request);
    }
    finally {
      $stack->pop();
    }
    return (string) $response->getContent();
  }

  /**
   * Gets the POST values of a form rendered in the HTML.
   *
   * @return array<string, string>
   *   The hidden values (form_id, form_build_id, form_token) and the button.
   */
  protected function hiddenValues(string $html): array {
    $values = ['op' => 'Archive and send'];
    foreach (['form_id', 'form_build_id', 'form_token'] as $name) {
      $this->assertSame(1, preg_match('/name="' . $name . '" value="([^"]*)"/', $html, $match), $name . ' is in the markup');
      $values[$name] = html_entity_decode($match[1]);
    }
    return $values;
  }

  /**
   * Gets the archive entry of the edit pane, if offered.
   */
  protected function archiveEntry(Project $project): ?array {
    $build = $this->container->get('plugin.manager.manager_context_panes')->createInstance('edit')->build($project);
    return $build['content']['actions']['archive']['button'] ?? NULL;
  }

  /**
   * Reloads a project from storage.
   */
  protected function reload(Project $project): Project {
    $storage = $this->container->get('entity_type.manager')->getStorage('project');
    $storage->resetCache([$project->id()]);
    $fresh = $storage->load($project->id());
    $this->assertInstanceOf(Project::class, $fresh);
    return $fresh;
  }

  /**
   * A supervisor sees the archive link on open, ongoing and completed projects.
   */
  public function testArchiveLinkShown(): void {
    foreach ([ProjectState::Open, ProjectState::Ongoing, ProjectState::Completed] as $state) {
      $project = $this->actAsSupervisorOf($state);
      $entry = $this->archiveEntry($project);
      $this->assertNotNull($entry, $state->value);
      $this->assertSame('archive', $entry['#attributes']['data-pane-view']);
      $this->assertSame('Archive', (string) $entry['#title']);
    }
  }

  /**
   * The mail pane shows no form for archive mails; only the editor sends them.
   */
  public function testMailPaneRefusesArchiveMails(): void {
    $project = $this->actAsSupervisorOf(ProjectState::Open);
    $html = $this->pane($project, ['type' => 'mail', 'view' => 'mail', 'subtype' => 'archive_custom']);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('This mail cannot be sent', $html);
  }

  /**
   * Denied cases show no link, and a POST sends nothing and archives nothing.
   */
  public function testArchiveDeniedCases(): void {

    $allowed = $this->actAsSupervisorOf(ProjectState::Open);
    $supervisor = $this->container->get('current_user')->getAccount();
    $query = ['view' => 'archive', 'reason' => 'custom'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($allowed, $query));

    $cases = [];
    foreach (['draft' => ProjectState::Draft, 'pending' => ProjectState::Pending, 'declined' => ProjectState::Declined] as $label => $state) {
      $cases[$label] = [$this->createArchivableProject($state), $supervisor];
    }
    $archived = $this->createArchivableProject(ProjectState::Open);
    $archived->setArchived()->save();
    $cases['archived'] = [$archived, $supervisor];
    // The assigned manager without the archive permission.
    $project = $this->createArchivableProject(ProjectState::Open);
    $manager = $this->accountWith($project->getOwner()->getManager(), [
      'general manager access',
      'send project mails',
      'view correspondence',
    ]);
    $cases['manager'] = [$project, $manager];

    foreach ($cases as $label => [$project, $account]) {
      $this->container->get('current_user')->setAccount($account);
      $was_archived = $this->reload($project)->isArchived();
      $this->assertNull($this->archiveEntry($project), $label);
      foreach ([NULL, $post] as $body) {
        $html = $this->pane($project, $query, $body);
        $this->assertStringNotContainsString('<form', $html, $label);
        $this->assertStringNotContainsString('archived', $html, $label);
      }
      $this->assertSame([], $this->loadEntries($project->id()), $label);
      $this->assertSame(0, $this->archiveEvents, $label);
      $this->assertSame($was_archived, $this->reload($project)->isArchived(), $label);
    }
  }

  /**
   * A form API POST of the editor archives and mails nothing.
   *
   * Only the project:archive resource archives; the pane script posts to it.
   */
  public function testFormPostDoesNothing(): void {

    $project = $this->actAsSupervisorOf(ProjectState::Completed);
    $query = ['view' => 'archive', 'reason' => 'external'];
    $html = $this->pane($project, $query);
    $this->assertStringContainsString('außerhalb von youvo', $html);
    $this->assertStringContainsString('data-rest-action="archive"', $html);
    $this->assertStringContainsString('data-reason="external"', $html);
    $this->assertSame(3, preg_match_all('/data-pane-view="archive"/', $html));

    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($html);
    $this->pane($project, $query, $post);
    $this->assertFalse($this->reload($project)->isArchived());
    $this->assertSame(0, $this->archiveEvents);
    $this->assertSame([], $this->loadEntries($project->id()));
  }

}
