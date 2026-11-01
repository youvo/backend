<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\Entity\LogPattern;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\manager\Controller\ContextPaneController;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
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
    $uri = '/project/manage/context/' . $project->id() . '?' . http_build_query($query + ['type' => 'transition']);
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
   * Builds the default view of the transition pane.
   */
  protected function transitionBuild(Project $project): array {
    return $this->container->get('plugin.manager.manager_context_panes')->createInstance('transition')->build($project);
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
      $build = $this->transitionBuild($project);
      $this->assertArrayHasKey('archive', $build['content'], $state->value);
      $this->assertSame('archive', $build['content']['archive']['#attributes']['data-pane-view']);
      $this->assertSame('Archive', (string) $build['content']['archive']['#title']);
    }
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
      $build = $this->transitionBuild($project);
      $this->assertArrayNotHasKey('archive', $build['content'] ?? [], $label);
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
   * A valid POST archives with the reason, then the edited mail is sent once.
   */
  public function testArchiveThenMail(): void {
    $project = $this->actAsSupervisorOf(ProjectState::Completed);
    $query = ['view' => 'archive', 'reason' => 'external'];
    $html = $this->pane($project, $query);
    $this->assertStringContainsString('außerhalb von youvo', $html);
    $post = ['subject' => 'Edited subject', 'body' => 'Edited body'] + $this->hiddenValues($html);
    $real = $this->container->get(ManagerMailerInterface::class);
    $mailer = $this->createMock(ManagerMailerInterface::class);
    $mailer->method('preview')->willReturnCallback(static fn (ProjectInterface $p, string $subtype) => $real->preview($p, $subtype));
    $mailer->method('send')->willReturnCallback(function (...$args) use ($real, $project): bool {
      // The project is archived and saved before the mail is sent.
      $this->assertTrue($this->reload($project)->isArchived());
      $this->captureMails($real);
      return $real->send(...$args);
    });
    $this->container->set(ManagerMailerInterface::class, $mailer);
    $html = $this->pane($project, $query, $post);

    $this->assertStringContainsString('The project was archived.', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertCount(1, $this->capturedMails);
    $this->assertSame('Edited subject', $this->capturedMails[0]['subject']);
    $this->assertTrue($this->reload($project)->isArchived());
    $this->assertTrue($this->reload($project)->lifecycle()->isCompleted());
    $this->assertSame(1, $this->archiveEvents);
    $logs = Log::loadMultiple();
    $this->assertCount(1, $logs);
    $this->assertSame(['reason' => 'external'], reset($logs)->getMisc());
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertSame('mailer.manager.archive_external', $entries[0]->getSubtype());
    $this->assertTrue($entries[0]->isSent());
  }

  /**
   * A failed archive sends no mail and shows the form with the input.
   */
  public function testArchiveFailureSendsNothing(): void {
    $project = $this->actAsSupervisorOf(ProjectState::Open);
    $query = ['view' => 'archive', 'reason' => 'custom'];
    $post = ['subject' => 'Edited subject', 'body' => 'Edited body'] + $this->hiddenValues($this->pane($project, $query));
    $this->container->get('event_dispatcher')->addListener(ProjectArchiveEvent::class, static function (): void {
      throw new \RuntimeException('boom');
    }, 3000);
    $html = $this->pane($project, $query, $post);

    $this->assertStringContainsString('The project could not be archived. No mail was sent.', $html);
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('Edited subject', $html);
    $this->assertFalse($this->reload($project)->isArchived());
    $this->assertSame([], $this->loadEntries($project->id()));
  }

}
