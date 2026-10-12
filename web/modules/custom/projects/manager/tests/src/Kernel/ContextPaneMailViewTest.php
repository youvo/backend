<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\manager\Controller\ContextPaneController;
use Drupal\manager\Form\ProjectMailForm;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the mail form inside the context pane, driven through the controller.
 *
 * @group manager
 */
class ContextPaneMailViewTest extends MailerKernelTestBase {

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
   * The project under test.
   */
  protected Project $project;

  /**
   * The assigned manager, who may send mails.
   */
  protected UserInterface $manager;

  /**
   * The session shared by all requests of a test (the CSRF seed lives in it).
   */
  protected ?Session $session = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses all permission checks: use it up.
    $this->createMailableCreative('Root');
  }

  /**
   * Creates the project and logs in its manager with the mail permission.
   */
  protected function actAsManager(ProjectState $state = ProjectState::Draft): void {
    $project = $this->createMailableProject($state);
    $this->assertInstanceOf(Project::class, $project);
    $this->project = $project;
    $this->manager = $this->accountWith($project->getOwner()->getManager(), [
      'general manager access',
      'send project mails',
      'view correspondence',
    ]);
    $this->container->get('current_user')->setAccount($this->manager);
  }

  /**
   * Runs the pane controller for a request and returns the HTML.
   *
   * @param array<string, string> $query
   *   Query parameters besides the pane type.
   * @param array<string, string>|null $post
   *   POST values, or NULL for a GET request.
   * @param \Drupal\projects\Entity\Project|null $project
   *   The project of the pane URL, defaults to the project under test.
   *
   * @return string
   *   The response body.
   */
  protected function pane(array $query = [], ?array $post = NULL, ?Project $project = NULL): string {
    $project ??= $this->project;
    $uri = '/project/manage/context/' . $project->id() . '?' . http_build_query(['type' => 'mail'] + $query);
    $request = Request::create($uri, $post === NULL ? 'GET' : 'POST', $post ?? []);
    $request->setSession($this->session ??= new Session(new MockArraySessionStorage()));
    // The form builder and validator keep per-request state (the validator
    // remembers validated form ids): start every request fresh.
    $this->container->set('form_builder', NULL);
    $this->container->set('form_validator', NULL);
    // FormState::$anyErrors is a process-wide static that a real request never
    // carries over.
    (new \ReflectionProperty(FormState::class, 'anyErrors'))->setValue(NULL, FALSE);
    $stack = $this->container->get('request_stack');
    $stack->push($request);
    try {
      $controller = ContextPaneController::create($this->container);
      $response = $controller->contextPane($project, $request);
    }
    finally {
      $stack->pop();
    }
    return (string) $response->getContent();
  }

  /**
   * Builds a form for a project and subtype with a form cache entry.
   *
   * Plain GET-built forms are not cached, but a cached build info (a form
   * state that was cached, as ajax or other modules do) is restored on a POST
   * with its form_build_id: the build info of that earlier build must not be
   * trusted.
   *
   * @return string
   *   The form_build_id of the cached form.
   */
  protected function cachedBuildId(Project $project, string $subtype): string {
    // Caching a form state is only allowed on non-GET requests.
    $request = Request::create('/', 'POST');
    $request->setSession($this->session ??= new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    $form_state = new FormState();
    $form_state->setRequestMethod('POST');
    $form_state->disableRedirect()->setCached();
    $form_state->addBuildInfo('args', [$project, $subtype]);
    $form = $this->container->get('form_builder')->buildForm(ProjectMailForm::class, $form_state);
    $this->container->get('request_stack')->pop();
    return $form['#build_id'];
  }

  /**
   * Gets the POST values of a form rendered in the HTML.
   *
   * @return array<string, string>
   *   The hidden values (form_id, form_build_id, form_token) and the button.
   */
  protected function hiddenValues(string $html): array {
    $values = ['op' => 'Send'];
    foreach (['form_id', 'form_build_id', 'form_token'] as $name) {
      $this->assertSame(1, preg_match('/name="' . $name . '" value="([^"]*)"/', $html, $match), $name . ' is in the markup');
      $values[$name] = html_entity_decode($match[1]);
    }
    return $values;
  }

  /**
   * Asserts that the messenger holds nothing.
   */
  protected function assertMessengerEmpty(): void {
    $this->assertSame([], $this->container->get('messenger')->all());
  }

  /**
   * The index view lists the reminders of the state with in-pane links.
   */
  public function testIndexListsReminders(): void {
    $this->actAsManager();
    $html = $this->pane(['view' => 'index']);
    $this->assertStringContainsString('data-pane-view="mail"', $html);
    $this->assertStringContainsString('data-pane-params="subtype=reminder_draft"', $html);
    $this->assertStringNotContainsString('data-pane-params="subtype=reminder_ongoing"', $html);
    $this->assertStringNotContainsString('use-ajax', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->loadEntries($this->project->id()));
  }

  /**
   * The mail view renders the prefilled form with real action and token.
   */
  public function testMailViewRendersPrefilledForm(): void {
    $this->actAsManager();
    $html = $this->pane(['view' => 'mail', 'subtype' => 'reminder_draft']);
    $this->assertStringContainsString('manager-pane-form', $html);
    $this->assertStringContainsString('[mail:project_link]', $html);
    // The type of the open form is marked in the list of types.
    $this->assertMatchesRegularExpression('/<a[^>]*aria-current="true"[^>]*data-pane-params="subtype=reminder_draft"|<a[^>]*data-pane-params="subtype=reminder_draft"[^>]*aria-current="true"/', $html);
    $this->assertStringNotContainsString('data-pane-view="index"', $html);
    // Placeholders are replaced by the isolated render.
    $this->assertStringNotContainsString('form_token_placeholder', $html);
    $this->assertStringNotContainsString('form_action_p_', $html);
    $this->hiddenValues($html);
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->loadEntries($this->project->id()));
  }

  /**
   * Without a view the pane opens the form of the first available mail.
   */
  public function testPaneOpensTheFormOfTheFirstMail(): void {
    $this->actAsManager();
    $html = $this->pane();
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('name="subject"', $html);
    $this->assertStringContainsString('aria-current="true"', $html);
    $this->assertSame([], $this->loadEntries($this->project->id()));
  }

  /**
   * A valid POST sends one mail, records it and shows the index.
   */
  public function testValidPostSendsOnceAndShowsIndex(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $post = ['subject' => 'Edited subject', 'body' => "Hello\n\n[mail:project_link]"] + $this->hiddenValues($this->pane($query));
    $html = $this->pane($query, $post);

    $this->assertStringContainsString('The mail was sent.', $html);
    $this->assertStringContainsString('data-pane-view="mail"', $html);
    $this->assertStringContainsString('Last manager mail sent', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertMessengerEmpty();
    $entries = array_values($this->loadEntries($this->project->id()));
    $this->assertCount(1, $entries);
    $this->assertSame('manager', $entries[0]->get('origin')->value);
    $this->assertSame((int) $this->manager->id(), (int) $entries[0]->get('sender')->target_id);
    $this->assertTrue($entries[0]->isSent());
    $this->assertSame('Edited subject', $entries[0]->get('subject')->value);
    $this->assertStringContainsString('organization@example.com', (string) $entries[0]->get('recipients')->value);
    // The sent text is the POSTed one, not a fresh preview.
    $this->assertStringContainsString('Hello', (string) $entries[0]->get('body')->value);
    $this->assertStringNotContainsString('Du hast auf youvo ein Projekt angelegt', (string) $entries[0]->get('body')->value);
  }

  /**
   * An invalid POST keeps the typed text, shows the error and sends nothing.
   */
  public function testInvalidPostKeepsTextAndSendsNothing(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $post = ['subject' => '', 'body' => 'My typed body'] + $this->hiddenValues($this->pane($query));
    $html = $this->pane($query, $post);
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('My typed body', $html);
    $this->assertStringContainsString('error', $html);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->loadEntries($this->project->id()));
  }

  /**
   * An account that does not manage the project gets nothing and sends nothing.
   */
  public function testPostByNonManagerRendersNothing(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($query));
    // Another user with the same permissions, but not the project's manager.
    $other = $this->accountWith($this->createMailableCreative('Other'), [
      'general manager access',
      'send project mails',
      'manage correspondence notes',
    ]);
    $this->container->get('current_user')->setAccount($other);
    foreach ([NULL, $post] as $body) {
      $html = $this->pane($query, $body);
      $this->assertStringNotContainsString('<form', $html);
      $this->assertStringNotContainsString('data-pane-view', $html);
      $this->assertStringNotContainsString('Subject', $html);
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->loadEntries($this->project->id()));

    // The same manager without the mail permission is denied as well.
    $this->container->get('current_user')->setAccount($this->accountWith($this->manager, ['general manager access']));
    $html = $this->pane($query, $post);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertSame([], $this->loadEntries($this->project->id()));
  }

  /**
   * A subtype that is not offered in the state renders and sends nothing.
   */
  public function testWrongStateSubtypeRendersNothing(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_ongoing'];
    $valid = $this->hiddenValues($this->pane(['subtype' => 'reminder_draft'] + $query));
    $post = ['subject' => 'S', 'body' => 'B'] + $valid;
    foreach ([NULL, $post] as $body) {
      $html = $this->pane($query, $body);
      $this->assertStringNotContainsString('<form', $html);
      $this->assertStringNotContainsString('manager-mail-pane-item', $html);
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->loadEntries($this->project->id()));
  }

  /**
   * A POST without a valid form token is rejected by the form API.
   */
  public function testPostWithoutValidTokenSendsNothing(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $valid = $this->hiddenValues($this->pane($query));
    $post = ['subject' => 'S', 'body' => 'B'] + $valid;

    $missing = $post;
    unset($missing['form_token']);
    $wrong = ['form_token' => 'xxxx'] + $post;
    $html = '';
    foreach ([$missing, $wrong] as $body) {
      $html = $this->pane($query, $body);
      $this->assertStringContainsString('<form', $html);
      $this->assertStringNotContainsString('The mail was sent.', $html);
      $this->assertMessengerEmpty();
      $this->assertSame([], $this->loadEntries($this->project->id()));
    }

    // A POST with the values of the form that was rendered again (a new
    // form_build_id, the same token) sends.
    $this->pane($query, ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($html));
    $this->assertCount(1, $this->loadEntries($this->project->id()));
  }

  /**
   * A failed send shows the form with the error and the text; a retry sends.
   */
  public function testFailedSendShowsFormAndRetrySends(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $post = ['subject' => 'Edited subject', 'body' => 'Edited body'] + $this->hiddenValues($this->pane($query));
    $this->useFailingTransport();
    $html = $this->pane($query, $post);
    $this->assertStringContainsString('The mail could not be sent. Please try again later.', $html);
    $this->assertStringContainsString('Edited subject', $html);
    $this->assertStringContainsString('Edited body', $html);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    // The transport error is not shown to the manager.
    $this->assertStringNotContainsString('127.0.0.1', $html);
    $this->assertMessengerEmpty();
    $entries = array_values($this->loadEntries($this->project->id()));
    $this->assertCount(1, $entries);
    $this->assertFalse($entries[0]->isSent());

    $this->config('mailer_transport.settings')->set('default_transport', 'null')->save();
    $html = $this->pane($query, $post);
    $this->assertStringContainsString('The mail was sent.', $html);
    $this->assertMessengerEmpty();
    $sent = array_filter($this->loadEntries($this->project->id()), static fn ($e) => $e->isSent());
    $this->assertCount(1, $sent);
  }

  /**
   * Submitted text is escaped when the form is rendered again.
   */
  public function testHostileTextIsEscaped(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $hostile = '"><script>alert(1)</script>';
    $post = ['subject' => $hostile, 'body' => ''] + $this->hiddenValues($this->pane($query));
    $html = $this->pane($query, $post);
    $this->assertStringContainsString('<form', $html);
    $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    $this->assertSame([], $this->loadEntries($this->project->id()));
  }

  /**
   * A form built while the state allowed it cannot be POSTed afterwards.
   */
  public function testStaleFormBuildIdSendsNothingAfterStateChange(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $post = ['subject' => 'S', 'body' => 'B', 'form_build_id' => $this->cachedBuildId($this->project, 'reminder_draft')] + $this->hiddenValues($this->pane($query));
    $fresh = $this->container->get('entity_type.manager')->getStorage('project')->load($this->project->id());
    $fresh->set('field_lifecycle', ProjectState::Open->value)->save();
    $html = $this->pane($query, $post, $fresh);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertStringNotContainsString('Edited', $html);
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->loadEntries($this->project->id()));
  }

  /**
   * A form_build_id of project X posted to the pane of project Y is denied.
   */
  public function testBuildIdOfOtherProjectIsDenied(): void {
    $this->actAsManager();
    $x = $this->project;
    $y = $this->createMailableProject(ProjectState::Draft);
    $this->assertInstanceOf(Project::class, $y);
    $admin = $this->accountWith($this->manager, [
      'general manager access',
      'send project mails',
      'administer projects',
    ]);
    $this->container->get('current_user')->setAccount($admin);
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $post = ['subject' => 'S', 'body' => 'B', 'form_build_id' => $this->cachedBuildId($x, 'reminder_draft')] + $this->hiddenValues($this->pane($query, NULL, $x));
    $html = $this->pane($query, $post, $y);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertSame([], $this->loadEntries($x->id()));
    $this->assertSame([], $this->loadEntries($y->id()));
    $this->assertMessengerEmpty();
  }

  /**
   * Replaying a successful POST (same form_build_id) sends nothing more.
   */
  public function testReplayedPostSendsOnlyOnce(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($query));
    $this->assertStringContainsString('The mail was sent.', $this->pane($query, $post));
    $this->assertCount(1, $this->loadEntries($this->project->id()));

    $html = $this->pane($query, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertCount(1, $this->loadEntries($this->project->id()));
    $this->assertMessengerEmpty();
  }

  /**
   * A POST without (or with a malformed) form_build_id sends nothing.
   */
  public function testMissingFormBuildIdSendsNothing(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_draft'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($query));
    $missing = $post;
    unset($missing['form_build_id']);
    foreach ([$missing, ['form_build_id' => ''] + $post, ['form_build_id' => 'junk'] + $post] as $body) {
      $html = $this->pane($query, $body);
      $this->assertStringContainsString('This form was already submitted.', $html);
      $this->assertStringNotContainsString('The mail was sent.', $html);
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->loadEntries($this->project->id()));
    // The untouched POST still sends: nothing was claimed by the failures.
    $this->assertStringContainsString('The mail was sent.', $this->pane($query, $post));
    $this->assertCount(1, $this->loadEntries($this->project->id()));
  }

}
