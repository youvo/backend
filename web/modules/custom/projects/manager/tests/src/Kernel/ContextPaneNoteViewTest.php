<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\manager\Controller\ContextPaneController;
use Drupal\manager\Form\ProjectNoteDeleteForm;
use Drupal\manager\Form\ProjectNoteForm;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the note forms inside the context panes, driven through the controller.
 *
 * Add runs in the mail pane (view note), edit and delete in the logbook pane
 * (views note_edit and note_delete with the note id).
 *
 * @group manager
 */
class ContextPaneNoteViewTest extends MailerKernelTestBase {

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
   * The note permissions of a manager.
   */
  protected const PERMISSIONS = ['general manager access', 'manage correspondence notes', 'view correspondence'];

  /**
   * The project under test.
   */
  protected Project $project;

  /**
   * The assigned manager, who may manage notes.
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
    $this->installEntitySchema('log');
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses all permission checks: use it up.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $this->project = $project;
    $this->manager = $this->accountWith($project->getOwner()->getManager(), self::PERMISSIONS);
    $this->actAs($this->manager);
  }

  /**
   * Switches the current user.
   */
  protected function actAs(AccountInterface $account): void {
    $this->container->get('current_user')->setAccount($account);
  }

  /**
   * Runs the pane controller for a request and returns the HTML.
   *
   * @param string $type
   *   The pane type, 'mail' (add) or 'logbook' (edit, delete).
   * @param array<string, string|int> $query
   *   Query parameters besides the pane type.
   * @param array<string, mixed>|null $post
   *   POST values, or NULL for a GET request.
   * @param \Drupal\projects\Entity\Project|null $project
   *   The project of the pane URL, defaults to the project under test.
   *
   * @return string
   *   The response body.
   */
  protected function pane(string $type, array $query = [], ?array $post = NULL, ?Project $project = NULL): string {
    $project ??= $this->project;
    $uri = '/project/manage/context/' . $project->id() . '?' . http_build_query(['type' => $type] + $query);
    $request = Request::create($uri, $post === NULL ? 'GET' : 'POST', $post ?? []);
    $request->setSession($this->session ??= new Session(new MockArraySessionStorage()));
    // The form builder and validator keep per-request state: start fresh.
    $this->container->set('form_builder', NULL);
    $this->container->set('form_validator', NULL);
    // FormState::$anyErrors is a process-wide static that a real request never
    // carries over.
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
   * Gets the hidden POST values of the form rendered in the HTML.
   *
   * @return array<string, string>
   *   The form_id, form_build_id, form_token and the button.
   */
  protected function hiddenValues(string $html, string $op): array {
    $values = ['op' => $op];
    foreach (['form_id', 'form_build_id', 'form_token'] as $name) {
      $this->assertSame(1, preg_match('/name="' . $name . '" value="([^"]*)"/', $html, $match), $name . ' is in the markup');
      $values[$name] = html_entity_decode($match[1]);
    }
    return $values;
  }

  /**
   * Gets the POST values of a note form.
   *
   * @return array<string, mixed>
   *   The values.
   */
  protected function notePost(string $html, string $subject, string $body, string $date = '2026-03-04', string $op = 'Save'): array {
    return ['subject' => $subject, 'body' => $body, 'occurred' => ['date' => $date, 'time' => '10:30:00']] + $this->hiddenValues($html, $op);
  }

  /**
   * Asserts that the messenger holds nothing.
   */
  protected function assertMessengerEmpty(): void {
    $this->assertSame([], $this->container->get('messenger')->all());
  }

  /**
   * Gets the notes of a project.
   *
   * @return \Drupal\correspondence\CorrespondenceInterface[]
   *   The notes.
   */
  protected function notes(?Project $project = NULL): array {
    $project ??= $this->project;
    return array_values(array_filter(
      $this->loadEntries($project->id()),
      static fn (CorrespondenceInterface $entry) => $entry->isNote(),
    ));
  }

  /**
   * Creates a note on a project.
   */
  protected function createNote(string $subject = 'Anruf', string $body = 'Rückruf vereinbart', ?Project $project = NULL): CorrespondenceInterface {
    $note = Correspondence::create([
      'type' => 'note',
      'project' => ($project ?? $this->project)->id(),
      'subject' => $subject,
      'body' => ['value' => $body, 'format' => 'plain_text'],
      'sender' => $this->manager->id(),
      'occurred' => 86400 * 365,
    ]);
    $note->save();
    return $note;
  }

  /**
   * Reloads a note from storage, NULL if it is gone.
   */
  protected function reload(CorrespondenceInterface $note): ?CorrespondenceInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('correspondence');
    $storage->resetCache([$note->id()]);
    $reloaded = $storage->load($note->id());
    return $reloaded instanceof CorrespondenceInterface ? $reloaded : NULL;
  }

  /**
   * Gets the expected timestamp of a date at 10:30.
   */
  protected function timestamp(string $date): int {
    $timezone = $this->config('system.date')->get('timezone.default') ?: date_default_timezone_get();
    return (new DrupalDateTime($date . ' 10:30:00', $timezone))->getTimestamp();
  }

  /**
   * Builds a note form with a form cache entry (see the mail view test).
   *
   * @return string
   *   The form_build_id of the cached form.
   */
  protected function cachedBuildId(Project $project, ?CorrespondenceInterface $note, string $form_class = ProjectNoteForm::class): string {
    $request = Request::create('/', 'POST');
    $request->setSession($this->session ??= new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    $form_state = new FormState();
    $form_state->setRequestMethod('POST');
    $form_state->disableRedirect()->setCached();
    $form_state->addBuildInfo('args', [$project, $note]);
    $form = $this->container->get('form_builder')->buildForm($form_class, $form_state);
    $this->container->get('request_stack')->pop();
    return $form['#build_id'];
  }

  /**
   * Creates a manager of another organization without administer projects.
   */
  protected function otherManager(): UserInterface {
    $other_project = $this->createMailableProject(ProjectState::Open);
    $this->assertNotSame($other_project->getOwner()->id(), $this->project->getOwner()->id());
    return $this->accountWith($other_project->getOwner()->getManager(), self::PERMISSIONS);
  }

  /**
   * Gets accounts that must get nothing and change nothing.
   *
   * @return array<string, \Drupal\Core\Session\AccountInterface>
   *   The accounts.
   */
  protected function deniedAccounts(): array {
    return [
      'other organization' => $this->otherManager(),
      'view only' => $this->accountWith($this->manager, ['general manager access', 'view correspondence']),
      'anonymous' => new AnonymousUserSession(),
    ];
  }

  /**
   * The mail pane index offers an in-pane link to the add form.
   */
  public function testIndexOffersInPaneAddLink(): void {
    $html = $this->pane('mail');
    $this->assertStringContainsString('data-pane-view="note"', $html);
    $this->assertStringContainsString('view=note', $html);
    $this->assertStringNotContainsString('use-ajax', $html);
    $this->assertMessengerEmpty();
  }

  /**
   * The add view renders the form with real action and token, saving nothing.
   */
  public function testAddViewRendersForm(): void {
    $html = $this->pane('mail', ['view' => 'note']);
    $this->assertStringContainsString('manager-pane-form', $html);
    $this->assertStringContainsString('name="subject"', $html);
    $this->assertStringContainsString('name="body"', $html);
    $this->assertStringContainsString('name="occurred[date]"', $html);
    $this->assertStringContainsString('data-pane-view="index"', $html);
    $this->assertStringNotContainsString('form_token_placeholder', $html);
    $this->assertStringNotContainsString('form_action_p_', $html);
    $this->hiddenValues($html, 'Save');
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->notes());
  }

  /**
   * A valid add POST saves one note as plain text and returns to the index.
   */
  public function testAddPostSavesNote(): void {
    $query = ['view' => 'note'];
    $post = $this->notePost($this->pane('mail', $query), 'Anruf <b>1</b>', "Rückruf <script>x</script>\nzweite Zeile");
    $html = $this->pane('mail', $query, $post);
    $this->assertStringContainsString('The note was saved.', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringContainsString('data-pane-view="note"', $html);
    $this->assertMessengerEmpty();

    $notes = $this->notes();
    $this->assertCount(1, $notes);
    $this->assertSame('Anruf <b>1</b>', $notes[0]->get('subject')->value);
    $this->assertSame("Rückruf <script>x</script>\nzweite Zeile", $notes[0]->get('body')->value);
    $this->assertSame('plain_text', $notes[0]->get('body')->format);
    $this->assertSame((int) $this->manager->id(), (int) $notes[0]->get('sender')->target_id);
    $this->assertSame($this->timestamp('2026-03-04'), $notes[0]->getOccurredTime());
  }

  /**
   * An invalid add POST keeps the text, shows errors and saves nothing.
   */
  public function testInvalidAddKeepsTextAndEscapesIt(): void {
    $query = ['view' => 'note'];
    $hostile = '"><script>alert(1)</script>';
    $form = $this->pane('mail', $query);
    foreach ([
      $this->notePost($form, '', 'My typed note'),
      $this->notePost($form, $hostile, ''),
      $this->notePost($form, 'T', 'B', '1969-12-31'),
      $this->notePost($form, 'T', 'B', '2038-01-02'),
    ] as $post) {
      $html = $this->pane('mail', $query, $post);
      $this->assertStringContainsString('<form', $html);
      $this->assertStringContainsString('error', $html);
      $this->assertStringNotContainsString('The note was saved.', $html);
      $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
      $this->assertMessengerEmpty();
      if ($post['subject'] === '') {
        $this->assertStringContainsString('My typed note', $html);
      }
      if ($post['subject'] === $hostile) {
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
      }
    }
    $this->assertSame([], $this->notes());
  }

  /**
   * Accounts that may not manage the notes get nothing and save nothing.
   */
  public function testAddDeniedForOthers(): void {
    $query = ['view' => 'note'];
    $post = $this->notePost($this->pane('mail', $query), 'S', 'B');
    foreach ($this->deniedAccounts() as $label => $account) {
      $this->actAs($account);
      foreach ([NULL, $post] as $body) {
        $html = $this->pane('mail', $query, $body);
        $this->assertStringNotContainsString('<form', $html, $label);
        $this->assertStringNotContainsString('data-pane-view', $html, $label);
        $this->assertStringNotContainsString('The note was saved.', $html, $label);
        $this->assertMessengerEmpty();
      }
    }
    $this->assertSame([], $this->notes());
  }

  /**
   * A POST without a valid form token is rejected by the form API.
   */
  public function testAddWithoutValidTokenSavesNothing(): void {
    $query = ['view' => 'note'];
    $post = $this->notePost($this->pane('mail', $query), 'S', 'B');
    $missing = $post;
    unset($missing['form_token']);
    $wrong = ['form_token' => 'xxxx'] + $post;
    foreach ([$missing, $wrong] as $body) {
      $html = $this->pane('mail', $query, $body);
      $this->assertStringContainsString('<form', $html);
      $this->assertStringNotContainsString('The note was saved.', $html);
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->notes());
  }

  /**
   * A replayed or double add POST creates exactly one note.
   */
  public function testDoubleAddSubmitCreatesOneNote(): void {
    $query = ['view' => 'note'];
    $post = $this->notePost($this->pane('mail', $query), 'S', 'B');
    $this->assertStringContainsString('The note was saved.', $this->pane('mail', $query, $post));
    $html = $this->pane('mail', $query, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertStringNotContainsString('The note was saved.', $html);
    $this->assertCount(1, $this->notes());
    $this->assertMessengerEmpty();
  }

  /**
   * A POST without (or with a malformed) form_build_id saves nothing.
   */
  public function testMissingFormBuildIdSavesNothing(): void {
    $query = ['view' => 'note'];
    $post = $this->notePost($this->pane('mail', $query), 'S', 'B');
    $missing = $post;
    unset($missing['form_build_id']);
    foreach ([$missing, ['form_build_id' => ''] + $post, ['form_build_id' => 'junk'] + $post] as $body) {
      $html = $this->pane('mail', $query, $body);
      $this->assertStringContainsString('This form was already submitted.', $html);
      $this->assertStringNotContainsString('The note was saved.', $html);
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->notes());
    // The untouched POST still works: nothing was claimed by the failures.
    $this->assertStringContainsString('The note was saved.', $this->pane('mail', $query, $post));
  }

  /**
   * A cached form of project X posted to the add pane of project Y is denied.
   */
  public function testAddBuildIdOfOtherProjectIsDenied(): void {
    $x = $this->project;
    $y = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $y);
    $admin = $this->accountWith($this->manager, [...self::PERMISSIONS, 'administer projects']);
    $this->actAs($admin);
    $query = ['view' => 'note'];
    $post = ['form_build_id' => $this->cachedBuildId($x, NULL)] + $this->notePost($this->pane('mail', $query, NULL, $x), 'S', 'B');
    $html = $this->pane('mail', $query, $post, $y);
    $this->assertStringNotContainsString('The note was saved.', $html);
    $this->assertSame([], $this->notes($x));
    $this->assertSame([], $this->notes($y));
    $this->assertMessengerEmpty();
  }

  /**
   * The edit view renders the form with the stored values.
   */
  public function testEditViewRendersPrefilledForm(): void {
    $note = $this->createNote();
    $html = $this->pane('logbook', ['view' => 'note_edit', 'id' => $note->id()]);
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('value="Anruf"', $html);
    $this->assertStringContainsString('Rückruf vereinbart', $html);
    $this->assertStringContainsString('data-pane-view="index"', $html);
    $this->assertStringNotContainsString('logbook-timeline-flex', $html);
    $this->hiddenValues($html, 'Save');
    $this->assertMessengerEmpty();
  }

  /**
   * A valid edit POST changes the note and returns to the timeline.
   */
  public function testEditPostChangesNote(): void {
    $note = $this->createNote();
    $query = ['view' => 'note_edit', 'id' => $note->id()];
    $post = $this->notePost($this->pane('logbook', $query), 'Neu <b>', '<script>y</script>', '2026-04-05');
    $html = $this->pane('logbook', $query, $post);
    $this->assertStringContainsString('The note was saved.', $html);
    $this->assertStringContainsString('logbook-timeline-flex', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringContainsString('Neu &lt;b&gt;', $html);
    $this->assertStringNotContainsString('<script>y</script>', $html);
    $this->assertMessengerEmpty();

    $notes = $this->notes();
    $this->assertCount(1, $notes);
    $this->assertSame('Neu <b>', $notes[0]->get('subject')->value);
    $this->assertSame('<script>y</script>', $notes[0]->get('body')->value);
    $this->assertSame('plain_text', $notes[0]->get('body')->format);
    $this->assertSame($this->timestamp('2026-04-05'), $notes[0]->getOccurredTime());
    // The author stays.
    $this->assertSame((int) $this->manager->id(), (int) $notes[0]->get('sender')->target_id);
  }

  /**
   * A failed edit keeps the typed text (escaped) and does not change the note.
   */
  public function testInvalidEditKeepsTextAndChangesNothing(): void {
    $note = $this->createNote();
    $query = ['view' => 'note_edit', 'id' => $note->id()];
    $hostile = '"><script>alert(1)</script>';
    $form = $this->pane('logbook', $query);
    $html = $this->pane('logbook', $query, $this->notePost($form, $hostile, ''));
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    $html = $this->pane('logbook', $query, $this->notePost($form, '', 'Typed body survives'));
    $this->assertStringContainsString('Typed body survives', $html);
    $this->assertStringNotContainsString('The note was saved.', $html);
    $this->assertMessengerEmpty();
    $this->assertSame('Anruf', $this->reload($note)->get('subject')->value);
  }

  /**
   * Edit and delete with a foreign note or a bad id render nothing.
   */
  public function testMismatchingProjectAndNoteIsRejected(): void {
    $own = $this->createNote('Own', 'x');
    $y = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $y);
    $foreign = $this->createNote('Foreign', 'x', $y);
    $mail = Correspondence::create(['type' => 'mail', 'project' => $this->project->id(), 'subject' => 'M']);
    $mail->save();
    $admin = $this->accountWith($this->manager, [...self::PERMISSIONS, 'administer projects']);
    $own_form = $this->pane('logbook', ['view' => 'note_edit', 'id' => $own->id()]);
    // Even an administrator gets nothing for a note of another project.
    foreach ([$this->manager, $admin] as $account) {
      $this->actAs($account);
      foreach (['note_edit' => 'Save', 'note_delete' => 'Delete'] as $view => $op) {
        foreach ([$foreign->id(), $mail->id(), 99999, 'abc', '1e3', '', '-1', '0'] as $id) {
          $query = ['view' => $view, 'id' => $id];
          foreach ([NULL, $this->notePost($own_form, 'Hacked', 'Hacked', '2026-03-04', $op)] as $body) {
            $html = $this->pane('logbook', $query, $body);
            $this->assertStringNotContainsString('<form', $html, "$view $id");
            $this->assertStringNotContainsString('logbook-timeline-flex', $html, "$view $id");
            $this->assertMessengerEmpty();
          }
        }
        // A missing id parameter as well.
        $this->assertStringNotContainsString('<form', $this->pane('logbook', ['view' => $view]));
      }
    }
    $this->assertSame('Foreign', $this->reload($foreign)->get('subject')->value);
    $this->assertNotNull($this->reload($foreign));
    $this->assertNotNull($this->reload($mail));
    $this->assertSame('Own', $this->reload($own)->get('subject')->value);
  }

  /**
   * Others get nothing for edit and delete and change nothing.
   */
  public function testEditAndDeleteDeniedForOthers(): void {
    $note = $this->createNote();
    $edit = ['view' => 'note_edit', 'id' => $note->id()];
    $delete = ['view' => 'note_delete', 'id' => $note->id()];
    $edit_post = $this->notePost($this->pane('logbook', $edit), 'Hacked', 'Hacked');
    $delete_post = $this->hiddenValues($this->pane('logbook', $delete), 'Delete');
    foreach ($this->deniedAccounts() as $label => $account) {
      $this->actAs($account);
      foreach ([[$edit, $edit_post], [$delete, $delete_post]] as [$query, $post]) {
        foreach ([NULL, $post] as $body) {
          $html = $this->pane('logbook', $query, $body);
          $this->assertStringNotContainsString('<form', $html, $label);
          $this->assertStringNotContainsString('was saved', $html, $label);
          $this->assertStringNotContainsString('was deleted', $html, $label);
          $this->assertMessengerEmpty();
        }
      }
    }
    $note = $this->reload($note);
    $this->assertNotNull($note);
    $this->assertSame('Anruf', $note->get('subject')->value);
  }

  /**
   * A cached edit form of one note cannot be used for another note.
   */
  public function testEditBuildIdOfOtherNoteIsDenied(): void {
    $first = $this->createNote('First', 'x');
    $second = $this->createNote('Second', 'x');
    $post = ['form_build_id' => $this->cachedBuildId($this->project, $first)]
      + $this->notePost($this->pane('logbook', ['view' => 'note_edit', 'id' => $first->id()]), 'Hacked', 'Hacked');
    $html = $this->pane('logbook', ['view' => 'note_edit', 'id' => $second->id()], $post);
    $this->assertStringNotContainsString('was saved', $html);
    $this->assertSame('Second', $this->reload($second)->get('subject')->value);
    $this->assertSame('First', $this->reload($first)->get('subject')->value);
    $this->assertMessengerEmpty();
  }

  /**
   * A cached delete form of one note cannot delete another note.
   */
  public function testDeleteBuildIdOfOtherNoteIsDenied(): void {
    $first = $this->createNote('First', 'x');
    $second = $this->createNote('Second', 'x');
    $post = ['form_build_id' => $this->cachedBuildId($this->project, $first, ProjectNoteDeleteForm::class)]
      + $this->hiddenValues($this->pane('logbook', ['view' => 'note_delete', 'id' => $first->id()]), 'Delete');
    $html = $this->pane('logbook', ['view' => 'note_delete', 'id' => $second->id()], $post);
    $this->assertStringNotContainsString('was deleted', $html);
    $this->assertNotNull($this->reload($second));
    $this->assertNotNull($this->reload($first));
    $this->assertMessengerEmpty();
  }

  /**
   * A replayed or double edit POST saves once and does not error.
   */
  public function testDoubleEditSubmitSavesOnce(): void {
    $note = $this->createNote();
    $query = ['view' => 'note_edit', 'id' => $note->id()];
    $post = $this->notePost($this->pane('logbook', $query), 'First save', 'x');
    $this->assertStringContainsString('The note was saved.', $this->pane('logbook', $query, $post));
    $changed = $this->notePost($this->pane('logbook', $query), 'Other', 'x');
    $this->pane('logbook', $query, $changed);
    // Replaying the first POST must not overwrite the later change.
    $html = $this->pane('logbook', $query, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertSame('Other', $this->reload($note)->get('subject')->value);
    $this->assertMessengerEmpty();
  }

  /**
   * A POST without form_build_id does not change the note.
   */
  public function testEditWithoutFormBuildIdChangesNothing(): void {
    $note = $this->createNote();
    $query = ['view' => 'note_edit', 'id' => $note->id()];
    $post = $this->notePost($this->pane('logbook', $query), 'Hacked', 'x');
    unset($post['form_build_id']);
    $html = $this->pane('logbook', $query, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertSame('Anruf', $this->reload($note)->get('subject')->value);
    $this->assertMessengerEmpty();
  }

  /**
   * The delete view asks for confirmation and deletes nothing on GET.
   */
  public function testDeleteViewIsConfirmationStep(): void {
    $note = $this->createNote('Titel <i>x</i>');
    $html = $this->pane('logbook', ['view' => 'note_delete', 'id' => $note->id()]);
    $this->assertStringContainsString('Do you really want to delete the note', $html);
    $this->assertStringContainsString('Titel &lt;i&gt;x&lt;/i&gt;', $html);
    $this->assertStringNotContainsString('<i>x</i>', $html);
    $this->assertStringContainsString('data-pane-view="index"', $html);
    $this->assertStringContainsString('value="Delete"', $html);
    $this->assertNotNull($this->reload($note));
    $this->assertMessengerEmpty();
  }

  /**
   * A delete POST removes the note and returns to the timeline.
   */
  public function testDeletePostRemovesNote(): void {
    $note = $this->createNote('Weg damit');
    $keep = $this->createNote('Bleibt');
    $query = ['view' => 'note_delete', 'id' => $note->id()];
    $post = $this->hiddenValues($this->pane('logbook', $query), 'Delete');
    $html = $this->pane('logbook', $query, $post);
    $this->assertStringContainsString('The note was deleted.', $html);
    $this->assertStringContainsString('logbook-timeline-flex', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('Weg damit', $html);
    $this->assertStringContainsString('Bleibt', $html);
    $this->assertMessengerEmpty();
    $this->assertNull($this->reload($note));
    $this->assertNotNull($this->reload($keep));

    // Replay: the note is gone, nothing renders and nothing else is deleted.
    $html = $this->pane('logbook', $query, $post);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertNotNull($this->reload($keep));
    $this->assertMessengerEmpty();
  }

  /**
   * A delete POST without a form_build_id deletes nothing.
   */
  public function testDeleteWithoutFormBuildIdDeletesNothing(): void {
    $note = $this->createNote();
    $query = ['view' => 'note_delete', 'id' => $note->id()];
    $post = $this->hiddenValues($this->pane('logbook', $query), 'Delete');
    unset($post['form_build_id']);
    $html = $this->pane('logbook', $query, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertNotNull($this->reload($note));
    $this->assertMessengerEmpty();
  }

  /**
   * The timeline links are in-pane links, only for accounts that may use them.
   */
  public function testTimelineLinks(): void {
    $note = $this->createNote();
    Correspondence::create(['type' => 'mail', 'project' => $this->project->id(), 'subject' => 'M'])->save();
    $html = $this->pane('logbook');
    $this->assertStringContainsString('logbook-timeline-flex', $html);
    $this->assertStringNotContainsString('use-ajax', $html);
    $this->assertStringNotContainsString('data-dialog-type', $html);
    foreach (['note_edit', 'note_delete'] as $view) {
      $this->assertStringContainsString('data-pane-view="' . $view . '"', $html);
      $this->assertStringContainsString('view=' . $view, $html);
    }
    $this->assertSame(2, substr_count($html, 'data-pane-params="id=' . $note->id() . '"'));
    $this->assertSame(1, substr_count($html, 'logbook-timeline-actions'));
    $this->assertMessengerEmpty();

    $this->actAs($this->accountWith($this->manager, ['general manager access', 'view correspondence']));
    $html = $this->pane('logbook');
    $this->assertStringContainsString('logbook-timeline-flex', $html);
    $this->assertStringNotContainsString('data-pane-view', $html);
    $this->assertStringNotContainsString('logbook-timeline-actions', $html);
  }

}
