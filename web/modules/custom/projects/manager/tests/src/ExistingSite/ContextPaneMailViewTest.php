<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\creatives\Entity\Creative;
use Drupal\manager\Form\ProjectMailForm;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\MailInterceptTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the mail pane and its form, driven through the controller.
 */
#[Group('manager')]
class ContextPaneMailViewTest extends ManagerExistingSiteTestBase {

  use ContextPaneRequestTrait;
  use MailInterceptTrait;

  /**
   * The project under test.
   */
  protected ProjectInterface $project;

  /**
   * The assigned manager, who may send mails.
   */
  protected Creative $manager;

  /**
   * The mail view of the draft reminder.
   */
  protected const array REMINDER = ['view' => 'mail', 'subtype' => 'reminder_draft'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->interceptMails();
  }

  /**
   * Creates the project and acts as its manager.
   */
  protected function actAsManager(ProjectState $state = ProjectState::Draft): void {
    $this->project = $this->createProject($state);
    $this->manager = $this->managerOf($this->project);
    $this->actAs($this->manager);
  }

  /**
   * Runs the mail pane of the project under test unless one is given.
   */
  protected function mailPane(array $query = [], ?array $post = NULL, ?ProjectInterface $project = NULL): string {
    return $this->pane($project ?? $this->project, ['type' => 'mail'] + $query, $post);
  }

  /**
   * Gets the POST values of the form in the HTML with a subject and body.
   *
   * @return array<string, string>
   *   The values.
   */
  protected function mailPost(string $html, string $subject = 'S', string $body = 'B'): array {
    return ['subject' => $subject, 'body' => $body] + $this->hiddenValues($html, 'Send');
  }

  /**
   * Takes the mail permission from the assigned manager.
   */
  protected function withoutMailPermission(): Creative {
    $this->manager->removeRole('manager');
    $this->manager->addRole($this->createRole(['general manager access', 'view correspondence']));
    $this->manager->save();
    return $this->manager;
  }

  /**
   * Records a mail of the project.
   */
  protected function recordMail(bool $sent, string $origin): void {
    $this->container->get('mailer.correspondence_recorder')
      ->recordMail($this->project, 'reminder_draft', ['a@example.com'], 'S', 'B', $sent, $origin, $origin === 'manager' ? (int) $this->manager->id() : NULL);
  }

  /**
   * The index view lists the reminders of the state with in-pane links.
   */
  public function testIndexListsReminders(): void {
    $this->actAsManager();
    $html = $this->mailPane(['view' => 'index']);
    $this->assertStringContainsString('data-pane-view="mail"', $html);
    $this->assertStringContainsString('data-pane-params="subtype=reminder_draft"', $html);
    $this->assertStringContainsString('view=mail', $html);
    $this->assertStringNotContainsString('data-pane-params="subtype=reminder_ongoing"', $html);
    $this->assertStringNotContainsString('use-ajax', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->entries($this->project));
  }

  /**
   * The mail view renders the prefilled form with real action and token.
   */
  public function testMailViewRendersPrefilledForm(): void {
    $this->actAsManager();
    $html = $this->mailPane(static::REMINDER);
    $this->assertStringContainsString('manager-pane-form', $html);
    $this->assertStringContainsString('[mail:project_link]', $html);
    // A reminder offers only the project link as placeholder.
    preg_match_all('/data-token="\[mail:([a-z_]+)\]"/', $html, $matches);
    $this->assertSame(['project_link'], $matches[1]);
    $this->assertStringContainsString(htmlspecialchars($this->project->getTitle(), ENT_QUOTES), $html);
    // The type of the open form is marked in the list of types.
    $this->assertMatchesRegularExpression('/<a[^>]*aria-current="true"[^>]*data-pane-params="subtype=reminder_draft"|<a[^>]*data-pane-params="subtype=reminder_draft"[^>]*aria-current="true"/', $html);
    $this->assertStringNotContainsString('data-pane-view="index"', $html);
    // Placeholders are replaced by the isolated render.
    $this->assertStringNotContainsString('form_token_placeholder', $html);
    $this->assertStringNotContainsString('form_action_p_', $html);
    $this->hiddenValues($html, 'Send');
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->entries($this->project));
  }

  /**
   * Without a view the pane opens the form of the first available mail.
   */
  public function testPaneOpensTheFormOfTheFirstMail(): void {
    $this->actAsManager();
    $html = $this->mailPane();
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('name="subject"', $html);
    $this->assertStringContainsString('aria-current="true"', $html);
    $this->assertSame([], $this->entries($this->project));
  }

  /**
   * A valid POST sends one mail, records it and shows the index.
   */
  public function testValidPostSendsOnceAndShowsIndex(): void {

    $this->actAsManager();
    $post = $this->mailPost($this->mailPane(static::REMINDER), 'Edited subject', "Hello\n\n[mail:project_link]");
    $html = $this->mailPane(static::REMINDER, $post);

    $this->assertStringContainsString('The mail was sent.', $html);
    $this->assertStringContainsString('data-pane-view="mail"', $html);
    $this->assertStringContainsString('Last manager mail sent', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertMessengerEmpty();

    $this->assertCount(1, $this->interceptedMails);
    $this->assertSame([$this->project->getOwner()->getEmail()], $this->interceptedMails[0]['to']);
    $this->assertSame('Edited subject', $this->interceptedMails[0]['subject']);
    $entries = $this->entries($this->project);
    $this->assertCount(1, $entries);
    $this->assertSame('manager', $entries[0]->get('origin')->value);
    $this->assertSame((int) $this->manager->id(), (int) $entries[0]->get('sender')->target_id);
    $this->assertTrue($entries[0]->isSent());
    $this->assertSame('Edited subject', $entries[0]->get('subject')->value);
    $this->assertStringContainsString($this->project->getOwner()->getEmail(), (string) $entries[0]->get('recipients')->value);
    // The sent text is the POSTed one, not a fresh preview.
    $this->assertStringContainsString('Hello', (string) $entries[0]->get('body')->value);
    $this->assertStringNotContainsString('Du hast auf youvo ein Projekt angelegt', (string) $entries[0]->get('body')->value);
  }

  /**
   * An invalid POST keeps the typed text, shows the error and sends nothing.
   */
  public function testInvalidPostKeepsTextAndSendsNothing(): void {
    $this->actAsManager();
    $form = $this->mailPane(static::REMINDER);
    foreach ([
      'empty subject' => $this->mailPost($form, '', 'My typed body'),
      'blank subject' => $this->mailPost($form, '   ', 'My typed body'),
      'long subject' => $this->mailPost($form, str_repeat('a', 300), 'My typed body'),
      'blank body' => $this->mailPost($form, 'ok', "  \n "),
      'empty body' => $this->mailPost($form, 'ok', ''),
    ] as $label => $post) {
      $html = $this->mailPane(static::REMINDER, $post);
      $this->assertStringContainsString('<form', $html, $label);
      $this->assertStringContainsString('error', $html, $label);
      $this->assertStringNotContainsString('The mail was sent.', $html, $label);
      if ($post['body'] === 'My typed body') {
        $this->assertStringContainsString('My typed body', $html, $label);
      }
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->entries($this->project));
    $this->assertSame([], $this->interceptedMails);
  }

  /**
   * Submitted text is escaped when the form is rendered again.
   */
  public function testHostileTextIsEscaped(): void {
    $this->actAsManager();
    $hostile = '"><script>alert(1)</script>';
    $html = $this->mailPane(static::REMINDER, $this->mailPost($this->mailPane(static::REMINDER), $hostile, ''));
    $this->assertStringContainsString('<form', $html);
    $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    $this->assertSame([], $this->entries($this->project));
  }

  /**
   * Line breaks in the subject are refused by the validation itself.
   *
   * The textfield element strips line breaks from a POST, so the validation
   * handler is called directly with the raw value (a header injection).
   */
  public function testSubjectLineBreaksAreRefused(): void {
    $this->actAsManager();
    $form_state = (new FormState())->setValues(['subject' => "A\r\nBcc: x@example.com", 'body' => 'x']);
    $form_state->set('preview', TRUE);
    $form_state->addBuildInfo('args', [$this->project, 'reminder_draft']);
    $form = [];
    ProjectMailForm::create($this->container)->validateForm($form, $form_state);
    $this->assertTrue($form_state->hasAnyErrors());
    $this->assertSame([], $this->entries($this->project));
  }

  /**
   * Accounts that may not mail the project get nothing and send nothing.
   */
  public function testPostByOthersRendersNothing(): void {

    $this->actAsManager();
    $post = $this->mailPost($this->mailPane(static::REMINDER));
    $denied = [
      'manager of no organization' => $this->createManager(),
      'assigned manager without the mail permission' => $this->withoutMailPermission(),
    ];
    foreach ($denied as $label => $account) {
      $this->actAs($account);
      foreach ([NULL, $post] as $body) {
        $html = $this->mailPane(static::REMINDER, $body);
        $this->assertStringNotContainsString('<form', $html, $label);
        $this->assertStringNotContainsString('data-pane-view', $html, $label);
        $this->assertStringNotContainsString('Subject', $html, $label);
        $this->assertMessengerEmpty();
      }
    }
    $this->assertSame([], $this->entries($this->project));
    $this->assertSame([], $this->interceptedMails);
  }

  /**
   * The form refuses to send for others, also when submitted directly.
   */
  public function testFormChecksAccessItself(): void {
    $this->actAsManager();
    $this->actAs($this->createManager());
    $form_state = (new FormState())->setValues(['subject' => 'S', 'body' => 'x'])->setProgrammed();
    $form_state->setUserInput(['form_build_id' => 'form-direct-' . $this->randomMachineName()]);
    $form_state->addBuildInfo('args', [$this->project, 'reminder_draft']);
    $form = [];
    // Even a direct submit (validation skipped) does not send.
    ProjectMailForm::create($this->container)->submitForm($form, $form_state);
    $this->assertTrue($form_state->get('send_denied'));
    $this->assertSame([], $this->entries($this->project));
    $this->assertSame([], $this->interceptedMails);
  }

  /**
   * A subtype that is not offered in the state renders and sends nothing.
   */
  public function testWrongStateSubtypeRendersNothing(): void {
    $this->actAsManager();
    $query = ['view' => 'mail', 'subtype' => 'reminder_ongoing'];
    $post = $this->mailPost($this->mailPane(static::REMINDER));
    foreach ([NULL, $post] as $body) {
      $html = $this->mailPane($query, $body);
      $this->assertStringNotContainsString('<form', $html);
      $this->assertStringNotContainsString('manager-mail-pane-item', $html);
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->entries($this->project));
  }

  /**
   * A POST without a valid form token is rejected by the form API.
   */
  public function testPostWithoutValidTokenSendsNothing(): void {

    $this->actAsManager();
    $post = $this->mailPost($this->mailPane(static::REMINDER));
    $missing = $post;
    unset($missing['form_token']);
    $wrong = ['form_token' => 'xxxx'] + $post;
    $html = '';
    foreach ([$missing, $wrong] as $body) {
      $html = $this->mailPane(static::REMINDER, $body);
      $this->assertStringContainsString('<form', $html);
      $this->assertStringNotContainsString('The mail was sent.', $html);
      $this->assertMessengerEmpty();
      $this->assertSame([], $this->entries($this->project));
    }

    // A POST with the values of the form that was rendered again (a new
    // form_build_id, the same token) sends.
    $this->mailPane(static::REMINDER, $this->mailPost($html));
    $this->assertCount(1, $this->entries($this->project));
    $this->assertCount(1, $this->interceptedMails);
  }

  /**
   * A failed send shows the form with the error and the text; a retry sends.
   */
  public function testFailedSendShowsFormAndRetrySends(): void {

    $this->actAsManager();
    $post = $this->mailPost($this->mailPane(static::REMINDER), 'Edited subject', 'Edited body');
    $this->failMails();
    $html = $this->mailPane(static::REMINDER, $post);
    $this->assertStringContainsString('The mail could not be sent. Please try again later.', $html);
    $this->assertStringContainsString('Edited subject', $html);
    $this->assertStringContainsString('Edited body', $html);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    // The transport error is not shown to the manager.
    $this->assertStringNotContainsString('127.0.0.1', $html);
    $this->assertMessengerEmpty();
    $entries = $this->entries($this->project);
    $this->assertCount(1, $entries);
    $this->assertFalse($entries[0]->isSent());

    $this->mailTransportDsn = 'null://null';
    $html = $this->mailPane(static::REMINDER, $post);
    $this->assertStringContainsString('The mail was sent.', $html);
    $this->assertMessengerEmpty();
    $sent = array_filter($this->entries($this->project), static fn ($entry) => $entry->isSent());
    $this->assertCount(1, $sent);
    $this->assertCount(2, $this->entries($this->project));
  }

  /**
   * A form built while the state allowed it cannot be POSTed afterwards.
   */
  public function testStaleFormBuildIdSendsNothingAfterStateChange(): void {
    $this->actAsManager();
    $post = ['form_build_id' => $this->cachedBuildId(ProjectMailForm::class, [$this->project, 'reminder_draft'])] + $this->mailPost($this->mailPane(static::REMINDER));
    $fresh = $this->reloadProject($this->project);
    $fresh->set('field_lifecycle', ProjectState::Open->value)->save();
    $html = $this->mailPane(static::REMINDER, $post, $fresh);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertMessengerEmpty();
    $this->assertSame([], $this->entries($this->project));
    $this->assertSame([], $this->interceptedMails);
  }

  /**
   * A form_build_id of project X posted to the pane of project Y is denied.
   */
  public function testBuildIdOfOtherProjectIsDenied(): void {
    $this->actAsManager();
    $x = $this->project;
    $y = $this->createProject(ProjectState::Draft);
    $this->actAs($this->createSupervisor());
    $post = ['form_build_id' => $this->cachedBuildId(ProjectMailForm::class, [$x, 'reminder_draft'])] + $this->mailPost($this->mailPane(static::REMINDER, NULL, $x));
    $html = $this->mailPane(static::REMINDER, $post, $y);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertSame([], $this->entries($x));
    $this->assertSame([], $this->entries($y));
    $this->assertSame([], $this->interceptedMails);
    $this->assertMessengerEmpty();
  }

  /**
   * Replaying a successful POST (same form_build_id) sends nothing more.
   */
  public function testReplayedPostSendsOnlyOnce(): void {

    $this->actAsManager();
    $post = $this->mailPost($this->mailPane(static::REMINDER));
    $this->assertStringContainsString('The mail was sent.', $this->mailPane(static::REMINDER, $post));
    $this->assertCount(1, $this->entries($this->project));

    $html = $this->mailPane(static::REMINDER, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertCount(1, $this->entries($this->project));
    $this->assertCount(1, $this->interceptedMails);
    $this->assertMessengerEmpty();
  }

  /**
   * A POST without (or with a malformed) form_build_id sends nothing.
   */
  public function testMissingFormBuildIdSendsNothing(): void {
    $this->actAsManager();
    $post = $this->mailPost($this->mailPane(static::REMINDER));
    $missing = $post;
    unset($missing['form_build_id']);
    foreach ([$missing, ['form_build_id' => ''] + $post, ['form_build_id' => 'junk'] + $post] as $body) {
      $html = $this->mailPane(static::REMINDER, $body);
      $this->assertStringContainsString('This form was already submitted.', $html);
      $this->assertStringNotContainsString('The mail was sent.', $html);
      $this->assertMessengerEmpty();
    }
    $this->assertSame([], $this->entries($this->project));
    // The untouched POST still sends: nothing was claimed by the failures.
    $this->assertStringContainsString('The mail was sent.', $this->mailPane(static::REMINDER, $post));
    $this->assertCount(1, $this->entries($this->project));
  }

  /**
   * The last sent manager mail is shown, failed and automatic ones are not.
   *
   * The assigned manager sees it on unpublished projects as well.
   */
  public function testLastSentLine(): void {
    foreach ([ProjectState::Draft, ProjectState::Pending] as $state) {
      $this->actAsManager($state);
      $this->recordMail(FALSE, 'manager');
      $this->recordMail(TRUE, 'automatic');
      $this->assertStringNotContainsString('manager-mail-pane-last', $this->mailPane(['view' => 'index']), $state->value);
      $this->recordMail(TRUE, 'manager');
      $this->assertStringContainsString('manager-mail-pane-last', $this->mailPane(['view' => 'index']), $state->value);
    }
  }

  /**
   * The empty text shows for an account with access only.
   */
  public function testEmptyTextOnlyWithAccess(): void {
    $this->actAsManager(ProjectState::Completed);
    $html = $this->mailPane(['view' => 'index']);
    $this->assertStringContainsString('manager-mail-pane-empty', $html);
    $this->assertStringNotContainsString('data-pane-view="mail"', $html);

    $this->actAs($this->createManager());
    $html = $this->mailPane(['view' => 'index']);
    $this->assertStringNotContainsString('manager-mail-pane-empty', $html);
    $this->assertStringNotContainsString('data-pane-view', $html);
  }

}
