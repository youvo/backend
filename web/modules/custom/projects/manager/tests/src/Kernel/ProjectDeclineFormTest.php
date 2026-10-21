<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\FormState;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\Entity\LogPattern;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\manager\Controller\ContextPaneController;
use Drupal\manager\Form\ProjectDeclineForm;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\user\UserInterface;
use Psr\Log\AbstractLogger;
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
   * Counts the decline items in the lifecycle history of a project.
   */
  protected function declineHistoryCount(Project $project): int {
    $count = 0;
    foreach ($this->reload($project)->lifecycle()->history() as $item) {
      $count += (int) ($item->transition === ProjectTransition::Decline->value);
    }
    return $count;
  }

  /**
   * The assigned manager sees the decline link on a prospect draft and pending.
   */
  public function testDeclineLinkShown(): void {
    foreach ([[ProjectState::Draft, TRUE], [ProjectState::Pending, FALSE]] as [$state, $prospect]) {
      $project = $this->actAsManagerOf($state, $prospect);
      $build = $this->transitionBuild($project);
      $this->assertArrayHasKey('decline', $build['content'], $state->value);
      $this->assertSame('decline', $build['content']['decline']['#attributes']['data-pane-view']);
      $this->assertSame('Decline', (string) $build['content']['decline']['#title']);
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
    ]);
    $cases['without transition permission'] = [$no_permission, $without];

    foreach ($cases as $label => [$project, $account]) {
      $this->container->get('current_user')->setAccount($account);
      $state = $project->get('field_lifecycle')->value;
      $build = $this->transitionBuild($project);
      $this->assertArrayNotHasKey('decline', $build['content'] ?? [], $label);
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
   * A valid POST sends the edited mail once, then declines with the reason.
   */
  public function testDeclineSendsThenDeclines(): void {
    $project = $this->actAsManagerOf(ProjectState::Pending);
    $query = ['view' => 'decline', 'reason' => 'deadline'];
    $post = ['subject' => 'Edited subject', 'body' => "Edited body\n\n[mail:project_link]"] + $this->hiddenValues($this->pane($project, $query));
    // The form renders a preview before it sends: register the capture on the
    // mailer right before the send.
    $real = $this->container->get(ManagerMailerInterface::class);
    $mailer = $this->createMock(ManagerMailerInterface::class);
    $mailer->method('preview')->willReturnCallback(static fn (ProjectInterface $p, string $subtype) => $real->preview($p, $subtype));
    $mailer->method('send')->willReturnCallback(function (...$args) use ($real): bool {
      $this->captureMails($real);
      return $real->send(...$args);
    });
    $this->container->set(ManagerMailerInterface::class, $mailer);
    $html = $this->pane($project, $query, $post);

    $this->assertStringContainsString('The project was declined and the mail was sent.', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertCount(1, $this->capturedMails);
    $this->assertSame('Edited subject', $this->capturedMails[0]['subject']);
    $this->assertTrue($this->reload($project)->lifecycle()->isDeclined());
    $this->assertSame(1, $this->declineEvents);
    $logs = Log::loadMultiple();
    $this->assertCount(1, $logs);
    $this->assertSame(['reason' => 'deadline'], reset($logs)->getMisc());
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertSame('manager', $entries[0]->get('origin')->value);
    $this->assertSame('mailer.manager.decline_deadline', $entries[0]->getSubtype());
    $this->assertTrue($entries[0]->isSent());
    $this->assertSame([], $this->container->get('messenger')->all());
  }

  /**
   * A failed send keeps the state and shows the form with the input.
   */
  public function testDeclineMailFailureKeepsState(): void {
    $project = $this->actAsManagerOf(ProjectState::Pending);
    $query = ['view' => 'decline', 'reason' => 'deadline'];
    $post = ['subject' => 'Edited subject', 'body' => 'Edited body'] + $this->hiddenValues($this->pane($project, $query));
    $this->useFailingTransport();
    $html = $this->pane($project, $query, $post);

    $this->assertStringContainsString('The mail could not be sent. The project was not declined.', $html);
    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('Edited subject', $html);
    $this->assertStringContainsString('Edited body', $html);
    $this->assertStringNotContainsString('127.0.0.1', $html);
    $this->assertTrue($this->reload($project)->lifecycle()->isPending());
    $this->assertSame(0, $this->declineEvents);
  }

  /**
   * A failed decline after the sent mail is reported to the manager.
   */
  public function testDeclineFailureAfterSend(): void {
    $project = $this->actAsManagerOf(ProjectState::Pending);
    $query = ['view' => 'decline', 'reason' => 'custom'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($project, $query));
    $this->container->get('event_dispatcher')->addListener(ProjectDeclineEvent::class, static function (): void {
      throw new \RuntimeException('boom');
    }, 3000);
    $html = $this->pane($project, $query, $post);

    $this->assertStringContainsString('The mail was sent, but the project could not be declined. Please contact a supervisor.', $html);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertTrue($this->reload($project)->lifecycle()->isPending());
    $this->assertCount(1, $this->loadEntries($project->id()));
  }

  /**
   * A listener failing after the decline is logged, but not reported.
   */
  public function testListenerFailureAfterDecline(): void {
    $project = $this->actAsManagerOf(ProjectState::Pending);
    $query = ['view' => 'decline', 'reason' => 'custom'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($project, $query));
    // Runs after the decline subscriber (priority 1000) saved the project.
    $this->container->get('event_dispatcher')->addListener(ProjectDeclineEvent::class, static function (): void {
      throw new \RuntimeException('boom');
    }, 500);
    $logger = new class() extends AbstractLogger {

      /**
       * The logged records.
       *
       * @var array<int, array{0: mixed, 1: string, 2: array<string, mixed>}>
       */
      public array $records = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, \Stringable|string $message, array $context = []): void {
        $this->records[] = [$level, (string) $message, $context];
      }

    };
    $this->container->get('logger.factory')->addLogger($logger);
    $html = $this->pane($project, $query, $post);

    $this->assertStringContainsString('The project was declined and the mail was sent.', $html);
    $this->assertStringNotContainsString('could not be declined', $html);
    $this->assertTrue($this->reload($project)->lifecycle()->isDeclined());
    $this->assertSame(1, $this->declineEvents);
    $this->assertCount(1, $this->loadEntries($project->id()));
    $errors = array_values(array_filter($logger->records, static fn (array $record) => ($record[2]['@message'] ?? NULL) === 'boom'));
    $this->assertCount(1, $errors);
    $this->assertSame(RfcLogLevel::ERROR, $errors[0][0]);
    $this->assertSame('manager', $errors[0][2]['channel']);
    $this->assertSame('The project @id was declined, but a listener of the decline failed: @message', $errors[0][1]);
  }

  /**
   * Replaying a POST after a failed decline does not send a second mail.
   *
   * The project stays pending, so only the form guard prevents the replay.
   */
  public function testDeclineReplayAfterFailedDecline(): void {
    $project = $this->actAsManagerOf(ProjectState::Pending);
    $query = ['view' => 'decline', 'reason' => 'custom'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($project, $query));
    $this->container->get('event_dispatcher')->addListener(ProjectDeclineEvent::class, static function (): void {
      throw new \RuntimeException('boom');
    }, 3000);
    $html = $this->pane($project, $query, $post);
    $this->assertStringContainsString('The mail was sent, but the project could not be declined.', $html);

    $html = $this->pane($this->reload($project), $query, $post);
    $this->assertStringContainsString('This form was already submitted.', $html);
    $this->assertStringNotContainsString('The mail was sent', $html);
    $this->assertCount(1, $this->loadEntries($project->id()));
    $this->assertTrue($this->reload($project)->lifecycle()->isPending());
    $this->assertSame(0, $this->declineHistoryCount($project));
  }

  /**
   * Replaying a successful POST sends and declines nothing more.
   */
  public function testDeclineReplay(): void {
    $project = $this->actAsManagerOf(ProjectState::Pending);
    $query = ['view' => 'decline', 'reason' => 'custom'];
    $post = ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($this->pane($project, $query));
    $this->assertStringContainsString('The project was declined and the mail was sent.', $this->pane($project, $query, $post));

    $html = $this->pane($this->reload($project), $query, $post);
    $this->assertStringNotContainsString('The project was declined and the mail was sent.', $html);
    $this->assertCount(1, $this->loadEntries($project->id()));
    $this->assertSame(1, $this->declineHistoryCount($project));
    $this->assertSame(1, $this->declineEvents);
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
   * The decline form refuses reminder subtypes.
   */
  public function testDeclineFormRefusesReminders(): void {
    $project = $this->actAsManagerOf(ProjectState::Draft, TRUE);
    $form_state = (new FormState())->setValues(['subject' => 'S', 'body' => 'B', 'form_build_id' => 'form-reminder'])->setProgrammed();
    $form_state->addBuildInfo('args', [$project, 'reminder_draft']);
    $this->container->get('form_builder')->submitForm(ProjectDeclineForm::create($this->container), $form_state);
    $this->assertNull($form_state->get('sent'));
    $this->assertSame([], $this->loadEntries($project->id()));
    $this->assertSame(0, $this->declineEvents);
  }

}
