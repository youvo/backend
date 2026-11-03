<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\creatives\Entity\Creative;
use Drupal\manager\Form\ProjectNoteDeleteForm;
use Drupal\manager\Form\ProjectNoteForm;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the note forms inside the logbook pane, driven through the controller.
 *
 * The view note_add shows the add form in the timeline, the views note_edit
 * and note_delete (with the note id) the edit form or the delete confirmation
 * in the row of the note.
 */
#[Group('manager')]
class ContextPaneNoteViewTest extends ManagerExistingSiteTestBase {

  use ContextPaneRequestTrait;

  /**
   * The project under test.
   */
  protected ProjectInterface $project;

  /**
   * The assigned manager, who may manage notes.
   */
  protected Creative $manager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->project = $this->createProject(ProjectState::Open);
    $this->manager = $this->managerOf($this->project);
    $this->actAs($this->manager);
  }

  /**
   * Runs the logbook pane of the project under test unless one is given.
   */
  protected function logbook(array $query = [], ?array $post = NULL, ?ProjectInterface $project = NULL): string {
    return $this->pane($project ?? $this->project, ['type' => 'logbook'] + $query, $post);
  }

  /**
   * Gets the POST values of a note form.
   *
   * @return array<string, mixed>
   *   The values.
   */
  protected function notePost(string $html, string $subject, string $body, string $date = '2026-03-04', string $op = 'Save', string $time = '10:30:00'): array {
    return ['subject' => $subject, 'body' => $body, 'occurred' => ['date' => $date, 'time' => $time]] + $this->hiddenValues($html, $op);
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
    $timezone = $this->container->get('config.factory')->get('system.date')->get('timezone.default') ?: date_default_timezone_get();
    return (new DrupalDateTime($date . ' 10:30:00', $timezone))->getTimestamp();
  }

  /**
   * Gets the manager of another organization.
   */
  protected function otherManager(): Creative {
    $other = $this->managerOf($this->createProject(ProjectState::Open));
    $this->assertNotSame($other->id(), $this->manager->id());
    return $other;
  }

  /**
   * Takes the note permission from the assigned manager; it keeps the view.
   */
  protected function withoutNotePermission(): Creative {
    $this->manager->removeRole('manager');
    $this->manager->addRole($this->createRole(['general manager access', 'view correspondence']));
    $this->manager->save();
    return $this->manager;
  }

  /**
   * Gets accounts that must get nothing and change nothing.
   *
   * The assigned manager loses the note permission, so the list comes last.
   *
   * @return array<string, \Drupal\Core\Session\AccountInterface>
   *   The accounts.
   */
  protected function deniedAccounts(): array {
    return [
      'other organization' => $this->otherManager(),
      'assigned manager without the note permission' => $this->withoutNotePermission(),
      'anonymous' => new AnonymousUserSession(),
    ];
  }

  /**
   * Submits a note form directly, past the pane and its checks.
   */
  protected function submitDirectly(AccountInterface $account, FormInterface $form, array $args, array $values): FormStateInterface {
    $this->actAs($account);
    $values += ['form_build_id' => 'form-direct-' . $this->randomMachineName()];
    $form_state = (new FormState())->setValues($values)->setProgrammed();
    $form_state->setUserInput($values);
    $form_state->addBuildInfo('args', $args);
    $this->container->get('form_builder')->submitForm($form, $form_state);
    return $form_state;
  }

  /**
   * The add view renders the form with real action and token, saving nothing.
   */
  public function testAddViewRendersForm(): void {
    $html = $this->logbook(['view' => 'note_add']);
    $this->assertStringContainsString('manager-pane-form', $html);
    // The form sits inside the timeline, in place of the add row.
    $this->assertStringContainsString('logbook-timeline-form', $html);
    $this->assertStringNotContainsString('logbook-timeline-add"', $html);
    $this->assertStringContainsString('name="subject"', $html);
    $this->assertStringContainsString('name="body"', $html);
    $this->assertStringContainsString('name="occurred[date]"', $html);
    $this->assertStringContainsString('data-pane-view="index"', $html);
    $this->assertStringNotContainsString('form_token_placeholder', $html);
    $this->assertStringNotContainsString('form_action_p_', $html);
    $this->hiddenValues($html, 'Save');
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->notes($this->project));
  }

  /**
   * A valid add POST saves one note as plain text and returns to the index.
   */
  public function testAddPostSavesNote(): void {

    $query = ['view' => 'note_add'];
    $post = $this->notePost($this->logbook($query), 'Anruf <b>1</b>', "Rückruf <script>x</script>\nzweite Zeile");
    $html = $this->logbook($query, $post);
    $this->assertStringContainsString('The note was saved.', $html);
    // A compact notice instead of the Drupal status message, and the saved
    // note is the one row marked for the highlight.
    $this->assertStringContainsString('pane-message-status', $html);
    $this->assertStringNotContainsString('class="messages', $html);
    $this->assertSame(1, substr_count($html, 'logbook-timeline-flex-row is-new'));
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringContainsString('data-pane-view="note_add"', $html);
    $this->assertMessengerEmpty();

    $notes = $this->notes($this->project);
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
    $query = ['view' => 'note_add'];
    $hostile = '"><script>alert(1)</script>';
    $form = $this->logbook($query);
    foreach ([
      $this->notePost($form, '', 'My typed note'),
      $this->notePost($form, $hostile, ''),
      $this->notePost($form, 'T', 'B', '1969-12-31'),
      $this->notePost($form, 'T', 'B', '2038-01-02'),
    ] as $post) {
      $html = $this->logbook($query, $post);
      $this->assertStringContainsString('<form', $html);
      // The error is shown at the field, not in a banner above the form.
      $this->assertStringContainsString('manager-pane-field-error', $html);
      $this->assertStringNotContainsString('pane-message-error', $html);
      $this->assertStringContainsString('aria-invalid="true"', $html);
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
    $this->assertSame([], $this->notes($this->project));
  }

  /**
   * Blank or too long text, a missing date and dates out of range are refused.
   *
   * The dates must lie between 1970-01-02 and 2037-12-31.
   */
  public function testValidationLimits(): void {

    $query = ['view' => 'note_add'];
    $form = $this->logbook($query);
    foreach ([
      'blank title' => $this->notePost($form, '   ', 'x'),
      'long title' => $this->notePost($form, str_repeat('a', 300), 'x'),
      'blank note' => $this->notePost($form, 'ok', " \n "),
      'no date' => ['occurred' => ['date' => '', 'time' => '']] + $this->notePost($form, 'ok', 'x'),
      'first day' => $this->notePost($form, 'ok', 'x', '1970-01-01', 'Save', '00:00:00'),
      'far future' => $this->notePost($form, 'ok', 'x', '9999-12-31'),
    ] as $label => $post) {
      $html = $this->logbook($query, $post);
      $this->assertStringContainsString('manager-pane-field-error', $html, $label);
      $this->assertStringNotContainsString('The note was saved.', $html, $label);
    }
    $this->assertSame([], $this->notes($this->project));

    foreach (['1970-01-02', '2037-12-31'] as $date) {
      $html = $this->logbook($query, $this->notePost($this->logbook($query), 'ok', 'x', $date));
      $this->assertStringContainsString('The note was saved.', $html, $date);
    }
    $this->assertCount(2, $this->notes($this->project));
    $this->assertMessengerEmpty();
  }

  /**
   * Accounts that may not manage the notes get nothing and save nothing.
   */
  public function testAddDeniedForOthers(): void {
    $query = ['view' => 'note_add'];
    $post = $this->notePost($this->logbook($query), 'S', 'B');
    foreach ($this->deniedAccounts() as $label => $account) {
      $this->actAs($account);
      foreach ([NULL, $post] as $body) {
        $html = $this->logbook($query, $body);
        $this->assertStringNotContainsString('<form', $html, $label);
        $this->assertStringNotContainsString('data-pane-view', $html, $label);
        $this->assertStringNotContainsString('The note was saved.', $html, $label);
        $this->assertMessengerEmpty();
      }
    }
    $this->assertSame([], $this->notes($this->project));
  }

  /**
   * A POST without a valid form token is rejected by the form API.
   */
  public function testAddWithoutValidTokenSavesNothing(): void {
    $query = ['view' => 'note_add'];
    $post = $this->notePost($this->logbook($query), 'S', 'B');
    $missing = $post;
    unset($missing['form_token']);
    $wrong = ['form_token' => 'xxxx'] + $post;
    foreach ([$missing, $wrong] as $body) {
      $html = $this->logbook($query, $body);
      $this->assertStringContainsString('<form', $html);
      $this->assertStringNotContainsString('The note was saved.', $html);
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->notes($this->project));
  }

  /**
   * A replayed add POST creates exactly one note.
   */
  public function testReplayedAddCreatesOneNote(): void {
    $query = ['view' => 'note_add'];
    $post = $this->notePost($this->logbook($query), 'S', 'B');
    $this->assertStringContainsString('The note was saved.', $this->logbook($query, $post));
    $html = $this->logbook($query, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertStringNotContainsString('The note was saved.', $html);
    $this->assertCount(1, $this->notes($this->project));
    $this->assertMessengerEmpty();
  }

  /**
   * A POST without (or with a malformed) form_build_id changes nothing.
   */
  public function testMissingFormBuildIdChangesNothing(): void {

    $note = $this->createNote($this->project);
    $add = ['view' => 'note_add'];
    $edit = ['view' => 'note_edit', 'id' => $note->id()];
    $delete = ['view' => 'note_delete', 'id' => $note->id()];
    $add_post = $this->notePost($this->logbook($add), 'S', 'B');
    $posts = [
      [$add, $add_post],
      [$edit, $this->notePost($this->logbook($edit), 'Changed', 'x')],
      [$delete, $this->hiddenValues($this->logbook($delete), 'Delete')],
    ];
    foreach ($posts as [$query, $post]) {
      $missing = $post;
      unset($missing['form_build_id']);
      foreach ([$missing, ['form_build_id' => ''] + $post, ['form_build_id' => 'junk'] + $post] as $body) {
        $html = $this->logbook($query, $body);
        $this->assertStringContainsString('This form was already submitted.', $html, $query['view']);
        $this->assertStringNotContainsString('The note was', $html, $query['view']);
        $this->assertMessengerEmpty();
      }
    }
    $notes = $this->notes($this->project);
    $this->assertCount(1, $notes);
    $this->assertSame('Anruf', $notes[0]->get('subject')->value);
    // The untouched POST still works: nothing was claimed by the failures.
    $this->assertStringContainsString('The note was saved.', $this->logbook($add, $add_post));
  }

  /**
   * A cached form of project X posted to the add pane of project Y is denied.
   */
  public function testAddBuildIdOfOtherProjectIsDenied(): void {
    $x = $this->project;
    $y = $this->createProject(ProjectState::Open);
    $this->actAs($this->createSupervisor());
    $query = ['view' => 'note_add'];
    $post = ['form_build_id' => $this->cachedBuildId(ProjectNoteForm::class, [$x, NULL])] + $this->notePost($this->logbook($query, NULL, $x), 'S', 'B');
    $html = $this->logbook($query, $post, $y);
    $this->assertStringNotContainsString('The note was saved.', $html);
    $this->assertSame([], $this->notes($x));
    $this->assertSame([], $this->notes($y));
    $this->assertMessengerEmpty();
  }

  /**
   * The edit view is the timeline with the form in the row of the note.
   *
   * The script takes only that row from the response, so other forms stay as
   * they are; the form tells the script the stored values (for the drafts).
   */
  public function testEditViewRendersPrefilledForm(): void {
    $note = $this->createNote($this->project);
    $html = $this->logbook(['view' => 'note_edit', 'id' => $note->id()]);
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('value="Anruf"', $html);
    $this->assertStringContainsString('Rückruf vereinbart', $html);
    $this->assertStringContainsString('data-pane-view="index"', $html);
    $this->assertStringContainsString('data-pane-row="note-' . $note->id() . '"', $html);
    // The form is in the row of the note, in the whole timeline.
    $this->assertStringContainsString('logbook-timeline-form" data-row-key="note-' . $note->id() . '"', $html);
    $this->assertSame(1, substr_count($html, '<form'));
    $this->assertStringContainsString('data-initial=', $html);
    $this->hiddenValues($html, 'Save');
    $this->assertMessengerEmpty();
  }

  /**
   * A valid edit POST changes the note and returns to the timeline.
   */
  public function testEditPostChangesNote(): void {

    $note = $this->createNote($this->project);
    $query = ['view' => 'note_edit', 'id' => $note->id()];
    $post = $this->notePost($this->logbook($query), 'Neu <b>', '<script>y</script>', '2026-04-05');
    $html = $this->logbook($query, $post);
    $this->assertStringContainsString('The note was saved.', $html);
    $this->assertStringContainsString('logbook-timeline-flex', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringContainsString('Neu &lt;b&gt;', $html);
    $this->assertStringNotContainsString('<script>y</script>', $html);
    $this->assertMessengerEmpty();

    $notes = $this->notes($this->project);
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
    $note = $this->createNote($this->project);
    $query = ['view' => 'note_edit', 'id' => $note->id()];
    $hostile = '"><script>alert(1)</script>';
    $form = $this->logbook($query);
    $html = $this->logbook($query, $this->notePost($form, $hostile, ''));
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    $html = $this->logbook($query, $this->notePost($form, '', 'Typed body survives'));
    $this->assertStringContainsString('Typed body survives', $html);
    $this->assertStringNotContainsString('The note was saved.', $html);
    $this->assertMessengerEmpty();
    $this->assertSame('Anruf', $this->reload($note)->get('subject')->value);
  }

  /**
   * Edit and delete with a foreign note or a bad id render nothing.
   */
  public function testMismatchingProjectAndNoteIsRejected(): void {

    $own = $this->createNote($this->project, 'Own', 'x');
    $y = $this->createProject(ProjectState::Open);
    $foreign = $this->createNote($y, 'Foreign', 'x');
    $mail = Correspondence::create(['type' => 'mail', 'project' => $this->project->id(), 'subject' => 'M']);
    $mail->save();
    $gone = $this->createNote($this->project, 'Gone', 'x');
    $gone_id = $gone->id();
    $gone->delete();
    $own_form = $this->logbook(['view' => 'note_edit', 'id' => $own->id()]);

    // Even a supervisor gets nothing for a note of another project.
    foreach ([$this->manager, $this->createSupervisor()] as $account) {
      $this->actAs($account);
      foreach (['note_edit' => 'Save', 'note_delete' => 'Delete'] as $view => $op) {
        foreach ([$foreign->id(), $mail->id(), $gone_id, 'abc', '1e3', '', '-1', '0'] as $id) {
          $query = ['view' => $view, 'id' => $id];
          foreach ([NULL, $this->notePost($own_form, 'Hacked', 'Hacked', '2026-03-04', $op)] as $body) {
            $html = $this->logbook($query, $body);
            $this->assertStringNotContainsString('<form', $html, "$view $id");
            $this->assertStringNotContainsString('logbook-timeline-flex', $html, "$view $id");
            $this->assertMessengerEmpty();
          }
        }
        // A missing id parameter as well.
        $this->assertStringNotContainsString('<form', $this->logbook(['view' => $view]));
      }
    }
    $this->assertSame('Foreign', $this->reload($foreign)->get('subject')->value);
    $this->assertNotNull($this->reload($mail));
    $this->assertSame('Own', $this->reload($own)->get('subject')->value);
  }

  /**
   * Others get nothing for edit and delete and change nothing.
   */
  public function testEditAndDeleteDeniedForOthers(): void {
    $note = $this->createNote($this->project);
    $edit = ['view' => 'note_edit', 'id' => $note->id()];
    $delete = ['view' => 'note_delete', 'id' => $note->id()];
    $edit_post = $this->notePost($this->logbook($edit), 'Hacked', 'Hacked');
    $delete_post = $this->hiddenValues($this->logbook($delete), 'Delete');
    foreach ($this->deniedAccounts() as $label => $account) {
      $this->actAs($account);
      foreach ([[$edit, $edit_post], [$delete, $delete_post]] as [$query, $post]) {
        foreach ([NULL, $post] as $body) {
          $html = $this->logbook($query, $body);
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
    $first = $this->createNote($this->project, 'First', 'x');
    $second = $this->createNote($this->project, 'Second', 'x');
    $post = ['form_build_id' => $this->cachedBuildId(ProjectNoteForm::class, [$this->project, $first])]
      + $this->notePost($this->logbook(['view' => 'note_edit', 'id' => $first->id()]), 'Hacked', 'Hacked');
    $html = $this->logbook(['view' => 'note_edit', 'id' => $second->id()], $post);
    $this->assertStringNotContainsString('was saved', $html);
    $this->assertSame('Second', $this->reload($second)->get('subject')->value);
    $this->assertSame('First', $this->reload($first)->get('subject')->value);
    $this->assertMessengerEmpty();
  }

  /**
   * A cached delete form of one note cannot delete another note.
   */
  public function testDeleteBuildIdOfOtherNoteIsDenied(): void {
    $first = $this->createNote($this->project, 'First', 'x');
    $second = $this->createNote($this->project, 'Second', 'x');
    $post = ['form_build_id' => $this->cachedBuildId(ProjectNoteDeleteForm::class, [$this->project, $first])]
      + $this->hiddenValues($this->logbook(['view' => 'note_delete', 'id' => $first->id()]), 'Delete');
    $html = $this->logbook(['view' => 'note_delete', 'id' => $second->id()], $post);
    $this->assertStringNotContainsString('was deleted', $html);
    $this->assertNotNull($this->reload($second));
    $this->assertNotNull($this->reload($first));
    $this->assertMessengerEmpty();
  }

  /**
   * A replayed edit POST does not overwrite a later change.
   */
  public function testReplayedEditSavesOnce(): void {
    $note = $this->createNote($this->project);
    $query = ['view' => 'note_edit', 'id' => $note->id()];
    $post = $this->notePost($this->logbook($query), 'First save', 'x');
    $this->assertStringContainsString('The note was saved.', $this->logbook($query, $post));
    $changed = $this->notePost($this->logbook($query), 'Other', 'x');
    $this->logbook($query, $changed);
    $html = $this->logbook($query, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertSame('Other', $this->reload($note)->get('subject')->value);
    $this->assertMessengerEmpty();
  }

  /**
   * The delete view asks for confirmation in the row and deletes nothing.
   */
  public function testDeleteViewIsConfirmationStep(): void {
    $note = $this->createNote($this->project, 'Titel <i>x</i>');
    $html = $this->logbook(['view' => 'note_delete', 'id' => $note->id()]);
    $this->assertStringContainsString('Delete this note?', $html);
    // The row keeps the text of the note, so it is clear which one it is.
    $this->assertStringContainsString('is-deleting', $html);
    $this->assertStringContainsString('Titel &lt;i&gt;x&lt;/i&gt;', $html);
    $this->assertStringNotContainsString('<i>x</i>', $html);
    $this->assertStringContainsString('data-pane-row="note-' . $note->id() . '"', $html);
    $this->assertStringContainsString('data-pane-view="index"', $html);
    $this->assertStringContainsString('value="Delete"', $html);
    $this->assertNotNull($this->reload($note));
    $this->assertMessengerEmpty();
  }

  /**
   * A delete POST removes the note and returns to the timeline.
   */
  public function testDeletePostRemovesNote(): void {

    $note = $this->createNote($this->project, 'Weg damit');
    $keep = $this->createNote($this->project, 'Bleibt');
    $query = ['view' => 'note_delete', 'id' => $note->id()];
    $post = $this->hiddenValues($this->logbook($query), 'Delete');
    $html = $this->logbook($query, $post);
    $this->assertStringContainsString('The note was deleted.', $html);
    $this->assertStringContainsString('logbook-timeline-flex', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('Weg damit', $html);
    $this->assertStringContainsString('Bleibt', $html);
    $this->assertMessengerEmpty();
    $this->assertNull($this->reload($note));
    $this->assertNotNull($this->reload($keep));

    // Replay: the note is gone, nothing renders and nothing else is deleted.
    $html = $this->logbook($query, $post);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertNotNull($this->reload($keep));
    $this->assertMessengerEmpty();
  }

  /**
   * A supervisor who does not manage the organization manages its notes.
   */
  public function testSupervisorManagesNotes(): void {

    $note = $this->createNote($this->project);
    $this->actAs($this->createSupervisor());

    $edit = ['view' => 'note_edit', 'id' => $note->id()];
    $this->assertStringContainsString('The note was saved.', $this->logbook($edit, $this->notePost($this->logbook($edit), 'Supervisor edit', 'y')));
    $this->assertSame('Supervisor edit', $this->reload($note)->get('subject')->value);

    $add = ['view' => 'note_add'];
    $this->assertStringContainsString('The note was saved.', $this->logbook($add, $this->notePost($this->logbook($add), 'Supervisor add', 'y')));
    $this->assertCount(2, $this->notes($this->project));

    $delete = ['view' => 'note_delete', 'id' => $note->id()];
    $this->assertStringContainsString('The note was deleted.', $this->logbook($delete, $this->hiddenValues($this->logbook($delete), 'Delete')));
    $this->assertNull($this->reload($note));
    $this->assertCount(1, $this->notes($this->project));
  }

  /**
   * The forms check the access themselves, also when submitted directly.
   *
   * The pane checks the access before it builds a form; a form submitted past
   * the pane must still refuse other managers and a note deleted meanwhile.
   */
  public function testFormsCheckAccessThemselves(): void {

    $note = $this->createNote($this->project);
    $other = $this->otherManager();
    $edit = ProjectNoteForm::create($this->container);
    $delete = ProjectNoteDeleteForm::create($this->container);
    $values = ['subject' => 'Hacked', 'body' => 'x', 'occurred' => ['date' => '2026-03-04', 'time' => '10:30:00']];

    $this->assertTrue($this->submitDirectly($other, $edit, [$this->project, NULL], $values)->get('save_denied'));
    $this->assertTrue($this->submitDirectly($other, $edit, [$this->project, $note], $values)->get('save_denied'));
    $this->assertTrue($this->submitDirectly($other, $delete, [$this->project, $note], [])->get('delete_denied'));
    $this->assertSame('Anruf', $this->reload($note)->get('subject')->value);

    // A note that was deleted meanwhile cannot be saved or deleted again.
    $stale = clone $note;
    $note->delete();
    $this->assertTrue($this->submitDirectly($this->manager, $edit, [$this->project, $stale], $values)->get('save_denied'));
    $this->assertTrue($this->submitDirectly($this->manager, $delete, [$this->project, $stale], [])->get('delete_denied'));
    $this->assertSame([], $this->notes($this->project));
  }

  /**
   * The timeline links are in-pane links, only for accounts that may use them.
   */
  public function testTimelineLinks(): void {

    $note = $this->createNote($this->project);
    $this->createMailEntry($this->project);
    $html = $this->logbook();
    $this->assertStringContainsString('logbook-timeline-flex', $html);
    $this->assertStringNotContainsString('use-ajax', $html);
    $this->assertStringNotContainsString('data-dialog-type', $html);
    foreach (['note_add', 'note_edit', 'note_delete'] as $view) {
      $this->assertStringContainsString('data-pane-view="' . $view . '"', $html);
      $this->assertStringContainsString('view=' . $view, $html);
    }
    $this->assertSame(2, substr_count($html, 'data-pane-params="id=' . $note->id() . '"'));
    $this->assertSame(1, substr_count($html, 'logbook-timeline-actions'), 'Only the note row has actions.');
    $this->assertMessengerEmpty();

    $this->actAs($this->withoutNotePermission());
    $html = $this->logbook();
    $this->assertStringContainsString('logbook-timeline-flex', $html);
    $this->assertStringNotContainsString('data-pane-view', $html);
    $this->assertStringNotContainsString('logbook-timeline-actions', $html);
  }

}
