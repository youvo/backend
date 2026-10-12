<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\mailer\Manager\ManagerMailAccess;
use Drupal\manager\Form\ProjectNoteDeleteForm;
use Drupal\manager\Form\ProjectNoteForm;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;

/**
 * Tests the manager note forms and their access.
 *
 * @group manager
 */
class ProjectNoteFormTest extends MailerKernelTestBase {

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
  protected const NOTE_PERMISSIONS = ['manage correspondence notes', 'view correspondence'];

  /**
   * A submitted date and time.
   */
  protected const OCCURRED = ['date' => '2026-03-04', 'time' => '10:30:00'];

  /**
   * Creates an open project and signs in its manager with note permissions.
   */
  protected function projectWithManager(): ProjectInterface {
    // User 1 bypasses all permission checks, so do not let the manager be it.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->accountWith($project->getOwner()->getManager(), self::NOTE_PERMISSIONS);
    $this->container->get('current_user')->setAccount($manager);
    return $project;
  }

  /**
   * Gets form values for a note.
   */
  protected function values(string $subject, string $body, array $occurred = self::OCCURRED): array {
    return ['subject' => $subject, 'body' => $body, 'occurred' => $occurred];
  }

  /**
   * Gets the expected timestamp for a submitted date and time.
   */
  protected function timestamp(array $occurred): int {
    $timezone = $this->config('system.date')->get('timezone.default') ?: date_default_timezone_get();
    return (new DrupalDateTime($occurred['date'] . ' ' . $occurred['time'], $timezone))->getTimestamp();
  }

  /**
   * Loads the notes of a project.
   *
   * @return \Drupal\correspondence\CorrespondenceInterface[]
   *   The notes.
   */
  protected function notes(ProjectInterface $project): array {
    return array_values(array_filter(
      $this->loadEntries($project->id()),
      static fn (CorrespondenceInterface $entry) => $entry->isNote(),
    ));
  }

  /**
   * Adding creates a note of the current user with the chosen time.
   */
  public function testAddCreatesNote(): void {
    $project = $this->projectWithManager();
    $form_object = ProjectNoteForm::create($this->container);
    $form = $this->build($form_object, [$project, NULL]);
    $this->assertSame('textarea', $form['body']['#type']);
    $this->assertSame('datetime', $form['occurred']['#type']);
    $this->assertInstanceOf(DrupalDateTime::class, $form['occurred']['#default_value']);
    $this->assertTrue($form['subject']['#required']);
    $this->assertSame(255, $form['subject']['#maxlength']);

    $occurred = self::OCCURRED;
    $form_state = $this->submit($form_object, [$project, NULL], $this->values('Anruf', 'Rückruf vereinbart', $occurred));
    $this->assertFalse($form_state->hasAnyErrors());
    $notes = $this->notes($project);
    $this->assertCount(1, $notes);
    $this->assertSame('note', $notes[0]->bundle());
    $this->assertSame('Anruf', $notes[0]->get('subject')->value);
    $this->assertSame('Rückruf vereinbart', $notes[0]->get('body')->value);
    $this->assertSame('plain_text', $notes[0]->get('body')->format);
    $this->assertSame((string) $this->container->get('current_user')->id(), (string) $notes[0]->get('sender')->target_id);
    $this->assertSame($this->timestamp($occurred), $notes[0]->getOccurredTime());
  }

  /**
   * Editing prefills the form and changes subject, body and time.
   */
  public function testEditChangesNote(): void {
    $project = $this->projectWithManager();
    $form_object = ProjectNoteForm::create($this->container);
    $this->submit($form_object, [$project, NULL], $this->values('Alt', 'Alter Text'));
    $note = $this->notes($project)[0];

    $form = $this->build($form_object, [$project, $note]);
    $this->assertSame('Alt', $form['subject']['#default_value']);
    $this->assertSame('Alter Text', $form['body']['#default_value']);
    $this->assertSame($this->timestamp(self::OCCURRED), $form['occurred']['#default_value']->getTimestamp());

    $occurred = ['date' => '2026-04-05', 'time' => '08:15:00'];
    $form_state = $this->submit($form_object, [$project, $note], $this->values('Neu', 'Neuer Text', $occurred));
    $this->assertFalse($form_state->hasAnyErrors());
    $notes = $this->notes($project);
    $this->assertCount(1, $notes);
    $this->assertSame('Neu', $notes[0]->get('subject')->value);
    $this->assertSame('Neuer Text', $notes[0]->get('body')->value);
    $this->assertSame($this->timestamp($occurred), $notes[0]->getOccurredTime());
    // The author stays.
    $this->assertSame((string) $this->container->get('current_user')->id(), (string) $notes[0]->get('sender')->target_id);
  }

  /**
   * Deleting removes the note.
   */
  public function testDeleteRemovesNote(): void {
    $project = $this->projectWithManager();
    $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('S', 'B'));
    $note = $this->notes($project)[0];
    $form_object = ProjectNoteDeleteForm::create($this->container);
    $form = $this->build($form_object, [$project, $note]);
    $this->assertArrayHasKey('submit', $form['actions']);
    $this->assertArrayHasKey('cancel', $form['actions']);

    $form_state = $this->submit($form_object, [$project, $note], []);
    $this->assertFalse($form_state->hasAnyErrors());
    $this->assertSame([], $this->notes($project));
    $this->assertTrue($form_state->get('deleted'));
  }

  /**
   * A user without the permission cannot delete or edit through the forms.
   */
  public function testFormsDenyWithoutPermission(): void {
    $project = $this->projectWithManager();
    $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('S', 'B'));
    $note = $this->notes($project)[0];
    $viewer = $this->accountWith($this->createMailableCreative('Viewer'), ['view correspondence']);
    $this->container->get('current_user')->setAccount($viewer);

    $form_state = $this->submit(ProjectNoteForm::create($this->container), [$project, $note], $this->values('Hacked', 'B'));
    $this->assertTrue($form_state->get('save_denied'));
    $this->assertSame('S', $this->notes($project)[0]->get('subject')->value);

    $form_state = $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('New', 'B'));
    $this->assertTrue($form_state->get('save_denied'));
    $this->assertCount(1, $this->notes($project));

    $form_state = $this->submit(ProjectNoteDeleteForm::create($this->container), [$project, $note], []);
    $this->assertTrue($form_state->get('delete_denied'));
    $this->assertCount(1, $this->notes($project));
  }

  /**
   * Empty and too long titles and empty text are rejected.
   */
  public function testValidation(): void {
    $project = $this->projectWithManager();
    $form_object = ProjectNoteForm::create($this->container);
    $occurred = self::OCCURRED;
    foreach ([
      ['subject' => '', 'body' => 'x', 'occurred' => $occurred],
      ['subject' => '   ', 'body' => 'x', 'occurred' => $occurred],
      ['subject' => str_repeat('a', 300), 'body' => 'x', 'occurred' => $occurred],
      ['subject' => 'ok', 'body' => " \n ", 'occurred' => $occurred],
      ['subject' => 'ok', 'body' => 'x', 'occurred' => ['date' => '', 'time' => '']],
    ] as $values) {
      $form_state = $this->submit($form_object, [$project, NULL], $values);
      $this->assertTrue($form_state->hasAnyErrors(), json_encode($values));
    }
    $this->assertSame([], $this->notes($project));
  }

  /**
   * The stored body is the raw input as plain text and never HTML.
   */
  public function testBodyIsStoredRawAsPlainText(): void {
    $project = $this->projectWithManager();
    $form_object = ProjectNoteForm::create($this->container);
    $this->submit($form_object, [$project, NULL], [
      'subject' => '<b>Titel</b>',
      'body' => '<script>x</script>',
      'occurred' => self::OCCURRED,
    ]);
    $note = $this->notes($project)[0];
    $this->assertSame('<script>x</script>', $note->get('body')->value);
    $this->assertSame('plain_text', $note->get('body')->format);
    $this->assertSame('<b>Titel</b>', $note->get('subject')->value);
    // The form never offers a text format or markup for the note.
    $form = $this->build($form_object, [$project, $note]);
    $this->assertSame('textarea', $form['body']['#type']);
    $this->assertArrayNotHasKey('#format', $form['body']);
    $this->assertSame('<script>x</script>', $form['body']['#default_value']);
  }

  /**
   * Validation alone saves nothing; submit saves once per form state.
   *
   * The guard only covers a repeated submitForm() call on one in-memory form
   * state; replayed requests are rejected by Drupal's form cache.
   */
  public function testSaveHappensOnceInSubmit(): void {
    $project = $this->projectWithManager();
    $form_object = ProjectNoteForm::create($this->container);
    $form_state = (new FormState())->setValues([
      'form_build_id' => 'form-once-in-submit',
      'subject' => 'S',
      'body' => 'x',
      'occurred' => new DrupalDateTime('2026-03-04 10:30:00'),
    ])->setProgrammed();
    $form_state->setUserInput(['form_build_id' => 'form-once-in-submit']);
    $form_state->addBuildInfo('args', [$project, NULL]);
    $form = [];
    $form_object->validateForm($form, $form_state);
    $this->assertFalse($form_state->hasAnyErrors());
    $this->assertSame([], $this->notes($project));

    $form_object->submitForm($form, $form_state);
    $this->assertCount(1, $this->notes($project));
    $form_object->submitForm($form, $form_state);
    $this->assertCount(1, $this->notes($project), 'A repeated submitForm() on the same form state does not save again.');
  }

  /**
   * A later validator that fails prevents the save.
   */
  public function testLaterValidatorFailureSavesNothing(): void {
    $project = $this->projectWithManager();
    $form_object = new class($this->container->get('entity_type.manager'), $this->container->get('mailer.manager_mail_access'), $this->container->get('manager.form_once')) extends ProjectNoteForm {

      /**
       * {@inheritdoc}
       */
      public function validateForm(array &$form, FormStateInterface $form_state): void {
        parent::validateForm($form, $form_state);
        $form_state->setErrorByName('', 'Captcha failed.');
      }

    };
    $form_state = $this->submit($form_object, [$project, NULL], $this->values('S', 'x'));
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertSame([], $this->notes($project));
  }

  /**
   * Only the project's manager with the permission may manage its notes.
   */
  public function testAccessOfManagers(): void {
    $project = $this->projectWithManager();
    $manager = $project->getOwner()->getManager();
    $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('S', 'x'));
    $note = $this->notes($project)[0];
    $access = $this->container->get('mailer.manager_mail_access');
    $this->assertInstanceOf(ManagerMailAccess::class, $access);

    $this->assertTrue($access->noteAccess($manager, $project)->isAllowed());
    $this->assertTrue($access->noteEntryAccess($manager, $note)->isAllowed());

    // A manager of another organization is denied.
    $other = $this->accountWith($this->createMailableCreative('Other'), self::NOTE_PERMISSIONS);
    $other_project = $this->createMailableProject(ProjectState::Open);
    $this->assertNotSame($other_project->getOwner()->id(), $project->getOwner()->id());
    $other_manager = $this->accountWith($other_project->getOwner()->getManager(), self::NOTE_PERMISSIONS);
    foreach ([$other, $other_manager] as $account) {
      $this->assertFalse($access->noteAccess($account, $project)->isAllowed());
      $this->assertFalse($access->noteEntryAccess($account, $note)->isAllowed());
    }
    $this->assertTrue($access->noteAccess($other_manager, $other_project)->isAllowed());

    // An administrator with the permission who is not the manager is allowed.
    $admin = $this->accountWith($this->createMailableCreative('Admin'), [
      'manage correspondence notes',
      'administer projects',
    ]);
    $this->assertTrue($access->noteAccess($admin, $project)->isAllowed());
  }

  /**
   * Creates a note on the project through the form as its manager.
   */
  protected function createNoteOf(ProjectInterface $project): CorrespondenceInterface {
    $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('S', 'x'));
    return $this->notes($project)[0];
  }

  /**
   * The manager of another organization cannot change notes through the forms.
   *
   * A direct form submit bypasses the route access, so the entity access
   * checks in the forms are the protection under test.
   */
  public function testFormsDenyManagerOfAnotherOrganization(): void {
    $project = $this->projectWithManager();
    $note = $this->createNoteOf($project);
    $other_project = $this->createMailableProject(ProjectState::Open);
    $other = $this->accountWith($other_project->getOwner()->getManager(), self::NOTE_PERMISSIONS);
    $this->container->get('current_user')->setAccount($other);

    $form_state = $this->submit(ProjectNoteForm::create($this->container), [$project, $note], $this->values('Hacked', 'Hacked'));
    $this->assertTrue($form_state->get('save_denied'));
    $this->assertSame('S', $this->notes($project)[0]->get('subject')->value);

    $form_state = $this->submit(ProjectNoteDeleteForm::create($this->container), [$project, $note], []);
    $this->assertTrue($form_state->get('delete_denied'));
    $this->assertCount(1, $this->notes($project));
  }

  /**
   * The manager of another organization cannot add a note to the project.
   */
  public function testAddFormDeniesManagerOfAnotherOrganization(): void {
    $project = $this->projectWithManager();
    $other_project = $this->createMailableProject(ProjectState::Open);
    $other = $this->accountWith($other_project->getOwner()->getManager(), self::NOTE_PERMISSIONS);
    $this->container->get('current_user')->setAccount($other);
    $form_state = $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('S', 'x'));
    $this->assertTrue($form_state->get('save_denied'));
    $this->assertSame([], $this->notes($project));
  }

  /**
   * An administrator of projects can add, edit and delete notes.
   */
  public function testAdministratorCanManageNotes(): void {
    $project = $this->projectWithManager();
    $note = $this->createNoteOf($project);
    $permissions = [...self::NOTE_PERMISSIONS, 'administer projects'];
    $admin = $this->accountWith($this->createMailableCreative('Admin'), $permissions);
    $this->container->get('current_user')->setAccount($admin);

    $form_state = $this->submit(ProjectNoteForm::create($this->container), [$project, $note], $this->values('Admin edit', 'y'));
    $this->assertEmpty($form_state->get('save_denied'));
    $this->assertSame('Admin edit', $this->notes($project)[0]->get('subject')->value);
    $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('Admin add', 'y'));
    $this->assertCount(2, $this->notes($project));
    $form_state = $this->submit(ProjectNoteDeleteForm::create($this->container), [$project, $note], []);
    $this->assertEmpty($form_state->get('delete_denied'));
    $this->assertCount(1, $this->notes($project));
  }

  /**
   * A note cannot be edited through another project's route parameter.
   */
  public function testMismatchingProjectAndNoteIsRejected(): void {
    $project = $this->projectWithManager();
    $note = $this->createNoteOf($project);
    $other_project = $this->createMailableProject(ProjectState::Open);
    $form_state = $this->submit(ProjectNoteForm::create($this->container), [$other_project, $note], $this->values('Changed', 'y'));
    $this->assertTrue($form_state->get('save_denied'));
    $this->assertSame('S', $this->notes($project)[0]->get('subject')->value);
    // The matching pair is accepted.
    $form_state = $this->submit(ProjectNoteForm::create($this->container), [$project, $note], $this->values('Changed', 'y'));
    $this->assertEmpty($form_state->get('save_denied'));
    $this->assertSame('Changed', $this->notes($project)[0]->get('subject')->value);
  }

  /**
   * Dates outside a sane range are rejected.
   */
  public function testDateRange(): void {
    $project = $this->projectWithManager();
    $form_object = ProjectNoteForm::create($this->container);
    foreach (['1969-12-31', '1970-01-01', '2038-01-01', '9999-12-31'] as $date) {
      $values = $this->values('S', 'x', ['date' => $date, 'time' => '12:00:00']);
      $form_state = $this->submit($form_object, [$project, NULL], $values);
      $this->assertTrue($form_state->hasAnyErrors(), $date);
    }
    $this->assertSame([], $this->notes($project));
    foreach (['1970-01-02', '2037-12-31'] as $date) {
      $values = $this->values('S', 'x', ['date' => $date, 'time' => '12:00:00']);
      $form_state = $this->submit($form_object, [$project, NULL], $values);
      $this->assertFalse($form_state->hasAnyErrors(), $date);
    }
  }

  /**
   * The assigned manager without the permission is denied.
   */
  public function testManagerWithoutPermissionIsDenied(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['view correspondence']);
    $note = Correspondence::create(['type' => 'note', 'project' => $project->id(), 'subject' => 'N']);
    $note->save();
    $access = $this->container->get('mailer.manager_mail_access');
    $this->assertInstanceOf(ManagerMailAccess::class, $access);
    $this->assertFalse($access->noteAccess($manager, $project)->isAllowed());
    $this->assertFalse($access->noteEntryAccess($manager, $note)->isAllowed());
  }

  /**
   * A mail entry is not a note, a dangling parent is denied.
   */
  public function testEntryAccessRejectsMailsAndDanglingParents(): void {
    $project = $this->projectWithManager();
    $manager = $project->getOwner()->getManager();
    $access = $this->container->get('mailer.manager_mail_access');
    $mail = Correspondence::create(['type' => 'mail', 'project' => $project->id(), 'subject' => 'M']);
    $mail->save();
    $this->assertFalse($access->noteEntryAccess($manager, $mail)->isAllowed());
    $dangling = Correspondence::create(['type' => 'note', 'project' => 99999, 'subject' => 'N']);
    $this->assertFalse($access->noteEntryAccess($manager, $dangling)->isAllowed());
  }

  /**
   * The note access checks the panes rely on.
   */
  public function testNoteAccess(): void {
    $project = $this->projectWithManager();
    $manager = $project->getOwner()->getManager();
    $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('S', 'x'));
    $note = $this->notes($project)[0];
    $other = $this->accountWith($this->createMailableCreative('Other'), self::NOTE_PERMISSIONS);
    $access = $this->container->get('mailer.manager_mail_access');

    $this->assertTrue($access->noteAccess($manager, $project)->isAllowed());
    $this->assertFalse($access->noteAccess($other, $project)->isAllowed());
    $this->assertTrue($access->noteEntryAccess($manager, $note)->isAllowed());
    $this->assertFalse($access->noteEntryAccess($other, $note)->isAllowed());
  }

  /**
   * A submit without a usable form_build_id changes nothing (fail closed).
   */
  public function testMissingOrInvalidFormBuildIdChangesNothing(): void {
    $project = $this->projectWithManager();
    $note = $this->createNoteOf($project);
    foreach ([NULL, '', ['form-a'], 123, 'not-a-build-id', 'form-' . str_repeat('a', 300)] as $id) {
      $input = $this->values('Changed', 'y') + ($id === NULL ? [] : ['form_build_id' => $id]);
      foreach ([
        [ProjectNoteForm::class, [$project, NULL]],
        [ProjectNoteForm::class, [$project, $note]],
        [ProjectNoteDeleteForm::class, [$project, $note]],
      ] as [$class, $args]) {
        $form_state = (new FormState())->setValues($input)->setProgrammed();
        $form_state->setUserInput($input);
        $form_state->addBuildInfo('args', $args);
        $this->container->get('form_builder')->submitForm($class::create($this->container), $form_state);
        $this->assertTrue($form_state->get('already_submitted'), $class . ' ' . json_encode($id));
        $this->assertNull($form_state->get('saved'));
        $this->assertNull($form_state->get('deleted'));
      }
    }
    $notes = $this->notes($project);
    $this->assertCount(1, $notes);
    $this->assertSame('S', $notes[0]->get('subject')->value);
  }

  /**
   * Replaying a submit (same form_build_id) saves and deletes only once.
   */
  public function testReplayedSubmitActsOnlyOnce(): void {
    $project = $this->projectWithManager();
    $add = ['form_build_id' => 'form-replay-add'] + $this->values('S', 'x');
    $this->assertTrue($this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $add)->get('saved'));
    $this->assertTrue($this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $add)->get('already_submitted'));
    $this->assertCount(1, $this->notes($project));

    $note = $this->notes($project)[0];
    $edit = ['form_build_id' => 'form-replay-edit'] + $this->values('E', 'x');
    $this->assertTrue($this->submit(ProjectNoteForm::create($this->container), [$project, $note], $edit)->get('saved'));
    $this->assertTrue($this->submit(ProjectNoteForm::create($this->container), [$project, $note], $edit)->get('already_submitted'));

    $delete = ['form_build_id' => 'form-replay-delete'];
    $this->assertTrue($this->submit(ProjectNoteDeleteForm::create($this->container), [$project, $note], $delete)->get('deleted'));
    $this->assertSame([], $this->notes($project));
  }

  /**
   * A failed save releases the claim, an exception does as well.
   */
  public function testFailedSaveAndExceptionReleaseTheClaim(): void {
    $project = $this->projectWithManager();
    $values = ['form_build_id' => 'form-throw'] + $this->values('S', 'x');
    $real = $this->container->get('entity_type.manager');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('create')->willThrowException(new \RuntimeException('boom'));
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->willReturnCallback(static fn (string $type) => $type === 'correspondence' ? $storage : $real->getStorage($type));
    $manager->method('getAccessControlHandler')->willReturnCallback(static fn (string $type) => $real->getAccessControlHandler($type));
    $throwing = new ProjectNoteForm($manager, $this->container->get('mailer.manager_mail_access'), $this->container->get('manager.form_once'));
    $form_state = (new FormState())->setValues($values)->setProgrammed();
    $form_state->setUserInput($values);
    $form_state->addBuildInfo('args', [$project, NULL]);
    try {
      $this->container->get('form_builder')->submitForm($throwing, $form_state);
      $this->fail('The exception must propagate.');
    }
    catch (\RuntimeException) {
    }
    $this->assertSame([], $this->notes($project));
    $retry = $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $values);
    $this->assertTrue($retry->get('saved'), 'The claim was released by the exception.');
    $this->assertCount(1, $this->notes($project));
  }

  /**
   * A note that was deleted meanwhile cannot be edited or deleted again.
   */
  public function testDeletedNoteIsDenied(): void {
    $project = $this->projectWithManager();
    $note = $this->createNoteOf($project);
    $stale = clone $note;
    $note->delete();
    $this->assertTrue($this->submit(ProjectNoteForm::create($this->container), [$project, $stale], $this->values('Ghost', 'x'))->get('save_denied'));
    $this->assertTrue($this->submit(ProjectNoteDeleteForm::create($this->container), [$project, $stale], [])->get('delete_denied'));
    $this->assertSame([], $this->notes($project));
  }

  /**
   * Build arguments that differ from the pane request are refused.
   */
  public function testPaneRequestMustMatchTheBuildArguments(): void {
    $project = $this->projectWithManager();
    $note = $this->createNoteOf($project);
    $other = $this->createMailableProject(ProjectState::Open);
    $request = $this->container->get('request_stack')->getCurrentRequest();
    $this->assertNotNull($request);
    // The pane claims the request is for another project.
    $request->attributes->set(ProjectNoteForm::PANE_ATTRIBUTE, [(string) $other->id(), (string) $note->id()]);
    $this->assertTrue($this->submit(ProjectNoteForm::create($this->container), [$project, $note], $this->values('X', 'x'))->get('save_denied'));
    $this->assertTrue($this->submit(ProjectNoteDeleteForm::create($this->container), [$project, $note], [])->get('delete_denied'));
    // The pane claims the add form, but the build info holds a note.
    $request->attributes->set(ProjectNoteForm::PANE_ATTRIBUTE, [(string) $project->id(), '']);
    $this->assertTrue($this->submit(ProjectNoteForm::create($this->container), [$project, $note], $this->values('X', 'x'))->get('save_denied'));
    $this->assertSame('S', $this->notes($project)[0]->get('subject')->value);
    // Matching arguments are accepted.
    $request->attributes->set(ProjectNoteForm::PANE_ATTRIBUTE, [(string) $project->id(), (string) $note->id()]);
    $this->assertTrue($this->submit(ProjectNoteForm::create($this->container), [$project, $note], $this->values('Y', 'x'))->get('saved'));
  }

  /**
   * The forms are plain forms with a Cancel link back to the pane.
   */
  public function testFormsArePlainWithCancelLinks(): void {
    $project = $this->projectWithManager();
    $note = $this->createNoteOf($project);
    $cases = [
      [ProjectNoteForm::class, [$project, NULL], 'logbook'],
      [ProjectNoteForm::class, [$project, $note], 'logbook'],
      [ProjectNoteDeleteForm::class, [$project, $note], 'logbook'],
    ];
    foreach ($cases as [$class, $args, $pane]) {
      $form = $this->build($class::create($this->container), $args);
      $this->assertArrayNotHasKey('#ajax', $form['actions']['submit']);
      $this->assertContains('manager-pane-form', $form['#attributes']['class']);
      $this->assertArrayNotHasKey('status_messages', $form);
      $cancel = $form['actions']['cancel'];
      $this->assertSame('index', $cancel['#attributes']['data-pane-view']);
      $this->assertSame(['type' => $pane, 'view' => 'index'], $cancel['#url']->getOption('query'));
      $this->assertSame('manager.context_pane', $cancel['#url']->getRouteName());
    }
    // The messenger stays untouched by the forms.
    $this->submit(ProjectNoteForm::create($this->container), [$project, NULL], $this->values('', 'x'));
    $this->assertSame([], array_diff_key($this->container->get('messenger')->all(), ['error' => 1]));
    $this->assertArrayNotHasKey('status', $this->container->get('messenger')->all());
  }

  /**
   * Counts the submits to give each a form_build_id.
   */
  protected int $submits = 0;

  /**
   * Builds the form (form builder arguments travel in the build info).
   */
  protected function build(FormInterface $form_object, array $args): array {
    $form_state = new FormState();
    $form_state->addBuildInfo('args', $args);
    return $this->container->get('form_builder')->buildForm($form_object, $form_state);
  }

  /**
   * Submits the form programmatically.
   */
  protected function submit(FormInterface $form_object, array $args, array $values): FormStateInterface {
    // Every submit is a new browser form unless the test posts an id itself.
    $values += ['form_build_id' => 'form-test-' . ++$this->submits];
    $form_state = (new FormState())->setValues($values)->setProgrammed();
    $form_state->addBuildInfo('args', $args);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    return $form_state;
  }

}
