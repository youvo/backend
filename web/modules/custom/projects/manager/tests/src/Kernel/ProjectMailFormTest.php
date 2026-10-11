<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
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

    $form_state = $this->submit($form_object, $project, [
      'subject' => 'Betreff',
      'body' => "Hallo\n\n[mail:project_link]",
    ]);
    $this->assertFalse($form_state->hasAnyErrors());
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
   * A failed send keeps the form (error shown) and records a failed entry.
   */
  public function testFailedSendShowsError(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $this->useFailingTransport();
    $form_object = ProjectMailForm::create($this->container);
    $form_state = $this->submit($form_object, $project, ['subject' => 'S', 'body' => 'x']);
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertFalse(array_values($this->loadEntries($project->id()))[0]->isSent());
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
   * The ajax callback closes the dialog and refreshes, or keeps the form.
   */
  public function testAjaxCommands(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $form_object = ProjectMailForm::create($this->container);

    $form_state = $this->submit($form_object, $project, ['subject' => 'S', 'body' => 'x']);
    $form = $this->build($form_object, $project);
    $commands = $form_object->ajaxSubmit($form, $form_state)->getCommands();
    $this->assertSame(['closeDialog', 'managerRefresh'], array_column($commands, 'command'));
    $this->assertSame((string) $project->id(), $commands[1]['projectId']);

    $this->useFailingTransport();
    $form_state = $this->submit($form_object, $project, ['subject' => 'S', 'body' => 'x']);
    $response = $form_object->ajaxSubmit($form, $form_state);
    $names = array_column($response->getCommands(), 'command');
    $this->assertContains('insert', $names);
    $this->assertNotContains('closeDialog', $names);
    $this->assertNotContains('managerRefresh', $names);
    $this->assertSame('replaceWith', $response->getCommands()[0]['method']);
  }

  /**
   * The route is granted to the assigned manager in an offered state only.
   */
  public function testRouteAccess(): void {
    // User 1 bypasses all permission checks, so do not let the manager be it.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['send project mails']);
    $other = $this->accountWith($this->createMailableCreative('Other'), ['send project mails']);
    $this->container->get('router.builder')->rebuild();
    $access = $this->container->get('access_manager');
    $parameters = ['project' => $project->id(), 'subtype' => 'reminder_draft'];

    $this->assertTrue($access->checkNamedRoute('manager.mail_form', $parameters, $manager));
    $this->assertFalse($access->checkNamedRoute('manager.mail_form', $parameters, $other));
    $this->assertFalse($access->checkNamedRoute('manager.mail_form', ['subtype' => 'reminder_ongoing'] + $parameters, $manager));
  }

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
    $form_state = (new FormState())->setValues($values)->setProgrammed();
    $form_state->addBuildInfo('args', [$project, $subtype]);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    return $form_state;
  }

}
