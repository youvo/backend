<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\logbook\Entity\LogPattern;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\manager\Controller\ContextPaneController;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\Entity\RestResourceConfig;
use Drupal\rest\RestResourceConfigInterface;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the decline form in the transition pane, driven through the controller.
 *
 * @group manager
 */
class ProjectDeclineFormTest extends MailerKernelTestBase {

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
   * The number of dispatched decline events.
   */
  protected int $declineEvents = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('log');
    $values = Yaml::decode((string) file_get_contents(DRUPAL_ROOT . '/modules/custom/monitoring/logbook/config/install/logbook.log_pattern.project_decline.yml'));
    LogPattern::create(['tokens' => []] + array_diff_key($values, ['tokens' => 1, 'uuid' => 1]))->save();
    // The REST permission exists with the resource config.
    RestResourceConfig::create([
      'id' => 'project.decline',
      'plugin_id' => 'project:decline',
      'granularity' => RestResourceConfigInterface::RESOURCE_GRANULARITY,
      'configuration' => ['methods' => ['GET', 'POST'], 'formats' => ['json'], 'authentication' => ['cookie']],
    ])->save();
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses all permission checks: use it up.
    $this->createMailableCreative('Root');
    $this->container->get('event_dispatcher')->addListener(ProjectDeclineEvent::class, function (): void {
      $this->declineEvents++;
    }, 2000);
  }

  /**
   * Creates a saved project whose owner and manager have a mail address.
   */
  protected function createDeclinableProject(ProjectState $state, bool $prospect = FALSE): Project {
    $project = $this->createProject($state, $prospect);
    $organization = $project->getOwner();
    $organization->set('mail', 'organization@example.com')->set('field_contact', 'Olga Org')->save();
    $organization->getManager()->set('mail', 'manager@example.com')->set('field_name', 'Manu Manager')->save();
    $project->save();
    $this->assertInstanceOf(Project::class, $project);
    return $project;
  }

  /**
   * Gives an account the permissions of a manager who may decline.
   */
  protected function decliner(UserInterface $account): UserInterface {
    return $this->accountWith($account, [
      'general manager access',
      'send project mails',
      'view correspondence',
      WorkflowPermissions::useTransition(ProjectLifecycle::WORKFLOW_ID, ProjectTransition::Decline->value),
      'restful post project:decline',
    ]);
  }

  /**
   * Creates a project and logs in its manager as a decliner.
   */
  protected function actAsManagerOf(ProjectState $state, bool $prospect = FALSE): Project {
    $project = $this->createDeclinableProject($state, $prospect);
    $this->container->get('current_user')->setAccount($this->decliner($project->getOwner()->getManager()));
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
    $values = ['op' => 'Decline and send'];
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
    $pane = $this->container->get('plugin.manager.manager_context_panes')->createInstance('transition');
    $build = $pane->build($project);
    // The cheap check of the project list agrees with the built pane.
    $this->assertSame(!empty($build['content']), $pane->hasActions($project));
    return $build;
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
   * The assigned manager sees the decline link on a prospect draft and pending.
   */
  public function testDeclineLinkShown(): void {
    foreach ([[ProjectState::Draft, TRUE], [ProjectState::Pending, FALSE]] as [$state, $prospect]) {
      $project = $this->actAsManagerOf($state, $prospect);
      $build = $this->transitionBuild($project);
      $this->assertArrayHasKey('decline', $build['content']['actions'], $state->value);
      $this->assertSame('decline', $build['content']['actions']['decline']['button']['#attributes']['data-pane-view']);
      $this->assertSame('Decline', (string) $build['content']['actions']['decline']['button']['#title']);
    }
  }

  /**
   * Denied cases show no link, and a POST sends nothing and declines nothing.
   */
  public function testDeclineDeniedCases(): void {
    $allowed = $this->actAsManagerOf(ProjectState::Pending);
    $manager = $this->container->get('current_user')->getAccount();
    $query = ['view' => 'decline', 'reason' => 'custom'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($allowed, $query));

    $cases = [];
    foreach ([
      'non-prospect draft' => [ProjectState::Draft, FALSE],
      'open' => [ProjectState::Open, FALSE],
      'completed' => [ProjectState::Completed, FALSE],
      'declined' => [ProjectState::Declined, TRUE],
    ] as $label => [$state, $prospect]) {
      $project = $this->createDeclinableProject($state, $prospect);
      $project->getOwner()->set('field_manager', $manager)->save();
      $cases[$label] = [$project, $manager];
    }
    // A pending project of another organization.
    $other = $this->createDeclinableProject(ProjectState::Pending);
    $cases['manager of another organization'] = [$other, $manager];
    // An unassigned prospect, the account manages another organization.
    $unassigned = $this->createDeclinableProject(ProjectState::Draft, TRUE);
    $unassigned->getOwner()->set('field_manager', NULL)->save();
    $cases['unassigned prospect'] = [$unassigned, $manager];
    // The assigned manager without the decline transition permission.
    $no_permission = $this->createDeclinableProject(ProjectState::Pending);
    $without = $this->accountWith($no_permission->getOwner()->getManager(), [
      'general manager access',
      'send project mails',
      'view correspondence',
      'restful post project:decline',
    ]);
    $cases['without transition permission'] = [$no_permission, $without];
    // The assigned manager without the REST permission.
    $no_rest = $this->createDeclinableProject(ProjectState::Pending);
    $without_rest = $this->accountWith($no_rest->getOwner()->getManager(), [
      'general manager access',
      'send project mails',
      'view correspondence',
      WorkflowPermissions::useTransition(ProjectLifecycle::WORKFLOW_ID, ProjectTransition::Decline->value),
    ]);
    $cases['without REST permission'] = [$no_rest, $without_rest];

    foreach ($cases as $label => [$project, $account]) {
      $this->container->get('current_user')->setAccount($account);
      $state = $project->get('field_lifecycle')->value;
      $build = $this->transitionBuild($project);
      $this->assertArrayNotHasKey('decline', $build['content']['actions'] ?? [], $label);
      foreach ([NULL, $post] as $body) {
        $html = $this->pane($project, $query, $body);
        $this->assertStringNotContainsString('<form', $html, $label);
        $this->assertStringNotContainsString('declined', $html, $label);
      }
      $this->assertSame([], $this->loadEntries($project->id()), $label);
      $this->assertSame(0, $this->declineEvents, $label);
      $this->assertSame($state, $this->reload($project)->get('field_lifecycle')->value, $label);
    }
  }

  /**
   * Without a reason the view opens the custom decline with all reason links.
   */
  public function testDeclineDefaultReason(): void {
    $project = $this->actAsManagerOf(ProjectState::Draft, TRUE);
    $preview = $this->container->get(ManagerMailerInterface::class)->preview($project, 'decline_custom');
    $this->assertNotNull($preview);
    $html = $this->pane($project, ['view' => 'decline']);

    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('manager-project-decline-form', $html);
    $this->assertStringContainsString(htmlspecialchars($preview->subject, ENT_QUOTES), $html);
    $this->assertStringContainsString('Decline and send', $html);
    $this->assertSame(9, preg_match_all('/data-pane-view="decline"/', $html));
    $this->assertMatchesRegularExpression('/<a[^>]*aria-current="true"[^>]*data-pane-params="reason=custom"|<a[^>]*data-pane-params="reason=custom"[^>]*aria-current="true"/', $html);
    $this->assertSame(1, preg_match_all('/aria-current="true"/', $html));

    // An unknown reason gives an empty pane.
    $html = $this->pane($project, ['view' => 'decline', 'reason' => 'unknown']);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('data-pane-view', $html);
  }

  /**
   * The mail pane never offers or sends a decline mail.
   */
  public function testMailPaneRefusesDeclineMails(): void {
    $project = $this->actAsManagerOf(ProjectState::Draft, TRUE);
    $mail = ['type' => 'mail', 'view' => 'mail', 'subtype' => 'decline_custom'];
    $html = $this->pane($project, $mail);
    $this->assertStringNotContainsString('<form', $html);

    $reminder = $this->pane($project, ['subtype' => 'reminder_draft'] + $mail);
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($reminder);
    $post['op'] = 'Send';
    $html = $this->pane($project, $mail, $post);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertSame([], $this->loadEntries($project->id()));
    $this->assertTrue($this->reload($project)->lifecycle()->isDraft());
  }

  /**
   * A form API POST of the editor declines and mails nothing.
   *
   * Only the project:decline resource declines; the pane script posts to it.
   */
  public function testFormPostDoesNothing(): void {
    $project = $this->actAsManagerOf(ProjectState::Pending);
    $query = ['view' => 'decline', 'reason' => 'deadline'];
    $html = $this->pane($project, $query);
    $this->assertStringContainsString('data-rest-action="decline"', $html);
    $this->assertStringContainsString('data-reason="deadline"', $html);

    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($html);
    $this->pane($project, $query, $post);
    $this->assertTrue($this->reload($project)->lifecycle()->isPending());
    $this->assertSame(0, $this->declineEvents);
    $this->assertSame([], $this->loadEntries($project->id()));
  }

}
