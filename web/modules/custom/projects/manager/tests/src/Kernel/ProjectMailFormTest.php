<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\manager\Form\ProjectMailForm;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;

/**
 * Tests the manager mail form.
 *
 * @group manager
 */
class ProjectMailFormTest extends MailerKernelTestBase {

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
   * The form is prefilled from the preview and sending records a manager mail.
   */
  public function testPrefillAndSend(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $this->container->get('current_user')->setAccount($manager);

    $form_object = ProjectMailForm::create($this->container);
    $form = $this->build($form_object, $project);
    $this->assertStringContainsString($project->getTitle(), $form['subject']['#default_value']);
    $this->assertStringContainsString('[mail:project_link]', $form['body']['#default_value']);
    $this->assertSame('textarea', $form['body']['#type']);
    // A plain form for the pane: no ajax, a Cancel link back to the pane list.
    $this->assertArrayNotHasKey('#ajax', $form['actions']['submit']);
    $this->assertContains('manager-pane-form', $form['#attributes']['class']);
    $this->assertSame('index', $form['actions']['cancel']['#attributes']['data-pane-view']);

    $form_state = $this->submit($form_object, $project, [
      'subject' => 'Betreff',
      'body' => "Hallo\n\n[mail:project_link]",
    ]);
    $this->assertFalse($form_state->hasAnyErrors());
    $this->assertTrue($form_state->get('sent'));
    // The form does not use the messenger; the pane renders the messages.
    $this->assertSame([], $this->container->get('messenger')->all());
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertSame('manager', $entries[0]->get('origin')->value);
  }

  /**
   * Subject with line breaks and empty text are rejected.
   */
  public function testValidation(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $form_object = ProjectMailForm::create($this->container);
    foreach ([
      ['subject' => '', 'body' => 'x'],
      ['subject' => str_repeat('a', 300), 'body' => 'x'],
      ['subject' => 'ok', 'body' => "  \n "],
      ['subject' => 'ok', 'body' => ''],
    ] as $values) {
      $form_state = $this->submit($form_object, $project, $values);
      $this->assertTrue($form_state->hasAnyErrors(), json_encode($values));
    }
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * Line breaks in the subject are rejected even if the element let them pass.
   *
   * The textfield element already strips line breaks from submitted input, so
   * the validation handler is called directly with the raw value.
   */
  public function testSubjectLineBreaksRejectedByValidation(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $form_object = ProjectMailForm::create($this->container);
    $form_state = (new FormState())->setValues(['subject' => "A\r\nBcc: x@example.com", 'body' => 'x']);
    $form_state->set('preview', TRUE);
    $form_state->addBuildInfo('args', [$project, 'reminder_draft']);
    $form = [];
    $form_object->validateForm($form, $form_state);
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * A failed send rebuilds the form with the edited text and records it.
   *
   * A retry with a working transport sends and records a sent entry.
   */
  public function testFailedSendKeepsTextAndAllowsRetry(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $this->useFailingTransport();
    $form_object = ProjectMailForm::create($this->container);
    $values = ['subject' => 'Edited subject', 'body' => 'Edited body', 'form_build_id' => 'form-retry'];
    $form_state = $this->submit($form_object, $project, $values);
    $this->assertTrue($form_state->get('send_failed'));
    $this->assertTrue($form_state->isRebuilding());
    $this->assertFalse($form_state->hasAnyErrors());
    // The form adds no message itself (the pane does). The transport error of
    // symfony_mailer is on the messenger; the pane discards it.
    $this->assertArrayNotHasKey('status', $this->container->get('messenger')->all());
    $this->container->get('messenger')->deleteAll();
    $form = $form_state->getCompleteForm();
    $this->assertSame('Edited subject', $form['subject']['#value']);
    $this->assertSame('Edited body', $form['body']['#value']);
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertFalse($entries[0]->isSent());

    // Retry with a working transport.
    $this->config('mailer_transport.settings')->set('default_transport', 'null')->save();
    $values = ['subject' => 'Edited subject', 'body' => 'Edited body', 'form_build_id' => 'form-retry'];
    $form_state = $this->submit($form_object, $project, $values);
    $this->assertFalse($form_state->get('send_failed'));
    $this->assertFalse($form_state->isRebuilding());
    $sent = array_filter($this->loadEntries($project->id()), static fn ($e) => $e->isSent());
    $this->assertCount(1, $sent);
    $this->assertCount(2, $this->loadEntries($project->id()));
  }

  /**
   * Validation alone sends nothing; submit sends once per form state.
   *
   * The guard only covers a repeated submitForm() call on one in-memory form
   * state; replayed requests are rejected by Drupal's form cache.
   */
  public function testSendHappensOnceInSubmit(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $form_object = ProjectMailForm::create($this->container);

    $form_state = (new FormState())->setValues(['subject' => 'S', 'body' => 'x'])->setProgrammed();
    $form_state->setUserInput(['form_build_id' => 'form-once-in-submit']);
    $form_state->addBuildInfo('args', [$project, 'reminder_draft']);
    $form_state->set('preview', TRUE);
    $form = [];
    $form_object->validateForm($form, $form_state);
    $this->assertFalse($form_state->hasAnyErrors());
    $this->assertSame([], $this->loadEntries($project->id()));

    $form_object->submitForm($form, $form_state);
    $this->assertCount(1, $this->loadEntries($project->id()));
    $form_object->submitForm($form, $form_state);
    $this->assertCount(1, $this->loadEntries($project->id()), 'A repeated submitForm() on the same form state does not send again.');
  }

  /**
   * A later validator that fails prevents the send.
   */
  public function testLaterValidatorFailureSendsNothing(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $form_object = new class($this->container->get(ManagerMailerInterface::class), $this->container->get('mailer.late_tokens'), $this->container->get('mailer.manager_mail_access'), $this->container->get('entity_type.manager'), $this->container->get('manager.form_once')) extends ProjectMailForm {

      /**
       * {@inheritdoc}
       */
      public function validateForm(array &$form, FormStateInterface $form_state): void {
        parent::validateForm($form, $form_state);
        $form_state->setErrorByName('', 'Captcha failed.');
      }

    };
    $form_state = $this->submit($form_object, $project, ['subject' => 'S', 'body' => 'x']);
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * The restored project is reloaded and access is checked on actual values.
   *
   * A POST with a form_build_id restores the build info of the earlier GET.
   * If the project changed state meanwhile, the stale serialized project must
   * not be mailed.
   */
  public function testStaleProjectSendsNothing(): void {
    // User 1 bypasses all permission checks: use it up.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['send project mails']);
    $this->container->get('current_user')->setAccount($manager);
    $form_object = ProjectMailForm::create($this->container);

    // The allowed case sends.
    $form_state = $this->submit($form_object, $project, ['subject' => 'S', 'body' => 'x']);
    $this->assertTrue($form_state->get('sent'));
    $this->assertCount(1, $this->loadEntries($project->id()));

    // The project moves on in storage; the form still holds the old object.
    $fresh = $this->container->get('entity_type.manager')->getStorage('project')->load($project->id());
    $fresh->set('field_lifecycle', ProjectState::Open->value)->save();
    $form_state = $this->submit($form_object, $project, ['subject' => 'S', 'body' => 'x']);
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertNull($form_state->get('sent'));
    $this->assertCount(1, $this->loadEntries($project->id()), 'Nothing more was sent.');
  }

  /**
   * The send fails closed when the account may not mail the project.
   */
  public function testSubmitWithoutAccessSendsNothing(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Draft);
    $other = $this->accountWith($this->createMailableCreative('Other'), ['send project mails']);
    $this->container->get('current_user')->setAccount($other);
    $form_object = ProjectMailForm::create($this->container);
    $form_state = (new FormState())->setValues(['subject' => 'S', 'body' => 'x'])->setProgrammed();
    $form_state->addBuildInfo('args', [$project, 'reminder_draft']);
    $form = [];
    // Even a direct submit (validation skipped) does not send.
    $form_object->submitForm($form, $form_state);
    $this->assertTrue($form_state->get('send_denied'));
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * A whitespace-only subject is rejected.
   */
  public function testWhitespaceSubjectRejected(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $form_state = $this->submit(ProjectMailForm::create($this->container), $project, ['subject' => '   ', 'body' => 'x']);
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * A subtype that is not offered shows no fields and sends nothing.
   */
  public function testUnavailablePreviewDoesNotSend(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $form_object = ProjectMailForm::create($this->container);
    $form = $this->build($form_object, $project, 'reminder_ongoing');
    $this->assertArrayHasKey('unavailable', $form);
    $this->assertArrayNotHasKey('actions', $form);
    $this->assertArrayNotHasKey('body', $form);

    $form_state = $this->submit($form_object, $project, ['subject' => 'S', 'body' => 'x'], 'reminder_ongoing');
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * Mail access goes to the assigned manager in an offered state only.
   */
  public function testMailAccess(): void {
    // User 1 bypasses all permission checks, so do not let the manager be it.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['send project mails']);
    $other = $this->accountWith($this->createMailableCreative('Other'), ['send project mails']);
    $access = $this->container->get('mailer.manager_mail_access');

    $this->assertTrue($access->access($manager, $project, 'reminder_draft')->isAllowed());
    $this->assertFalse($access->access($other, $project, 'reminder_draft')->isAllowed());
    $this->assertFalse($access->access($manager, $project, 'reminder_ongoing')->isAllowed());
    // The assigned manager without the permission is denied.
    $this->assertFalse($access->access($this->accountWith($manager, []), $project, 'reminder_draft')->isAllowed());
    // An administrator with the permission who is not the manager is allowed.
    $admin = $this->accountWith($other, ['send project mails', 'administer projects']);
    $this->assertTrue($access->access($admin, $project, 'reminder_draft')->isAllowed());
  }

  /**
   * A submit without a usable form_build_id sends nothing (fail closed).
   */
  public function testMissingOrInvalidFormBuildIdSendsNothing(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    foreach ([NULL, '', ['form-a'], 123, 'not-a-build-id', 'form-' . str_repeat('a', 300)] as $id) {
      $form_object = ProjectMailForm::create($this->container);
      $form_state = (new FormState())->setValues(['subject' => 'S', 'body' => 'x'])->setProgrammed();
      $form_state->setUserInput(['subject' => 'S', 'body' => 'x'] + ($id === NULL ? [] : ['form_build_id' => $id]));
      $form_state->addBuildInfo('args', [$project, 'reminder_draft']);
      $this->container->get('form_builder')->submitForm($form_object, $form_state);
      $this->assertTrue($form_state->get('already_submitted'), json_encode($id));
      $this->assertNull($form_state->get('sent'));
    }
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * An exception while sending releases the claim, so a retry can send.
   */
  public function testExceptionDuringSendReleasesTheClaim(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $real = $this->container->get(ManagerMailerInterface::class);
    $throwing = $this->createMock(ManagerMailerInterface::class);
    $throwing->method('preview')->willReturnCallback(static fn (ProjectInterface $project, string $subtype) => $real->preview($project, $subtype));
    $throwing->method('send')->willThrowException(new \RuntimeException('boom'));
    $form_object = new ProjectMailForm($throwing, $this->container->get('mailer.late_tokens'), $this->container->get('mailer.manager_mail_access'), $this->container->get('entity_type.manager'), $this->container->get('manager.form_once'));
    $values = ['subject' => 'S', 'body' => 'x', 'form_build_id' => 'form-boom'];
    try {
      $this->submit($form_object, $project, $values);
      $this->fail('The exception must propagate.');
    }
    catch (\RuntimeException) {
    }
    $this->assertSame([], $this->loadEntries($project->id()));
    $form_state = $this->submit(ProjectMailForm::create($this->container), $project, $values);
    $this->assertTrue($form_state->get('sent'), 'The claim was released by the exception.');
    $this->assertCount(1, $this->loadEntries($project->id()));
  }

  /**
   * Counts the submits to give each a form_build_id.
   */
  protected int $submits = 0;

  /**
   * Builds the form (form builder arguments travel in the build info).
   */
  protected function build(ProjectMailForm $form_object, ProjectInterface $project, string $subtype = 'reminder_draft'): array {
    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$project, $subtype]);
    return $this->container->get('form_builder')->buildForm($form_object, $form_state);
  }

  /**
   * Submits the form programmatically.
   */
  protected function submit(ProjectMailForm $form_object, ProjectInterface $project, array $values, string $subtype = 'reminder_draft'): FormStateInterface {
    // Every submit is a new browser form unless the test posts an id itself.
    $values += ['form_build_id' => 'form-test-' . ++$this->submits];
    $form_state = (new FormState())->setValues($values)->setProgrammed();
    $form_state->addBuildInfo('args', [$project, $subtype]);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    return $form_state;
  }

}
