<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\creatives\Entity\Creative;
use Drupal\lifecycle\WorkflowPermissions;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\Tests\youvo\Traits\MailInterceptTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the decline and archive editors of the action panes.
 *
 * The decline editor is a view of the transition pane, the archive editor one
 * of the edit pane. Only the project:decline and project:archive resources
 * act; the pane script posts the edited mail to them. The editors themselves
 * never decline, archive or send.
 */
#[Group('manager')]
class ProjectDeclineArchiveFormTest extends ManagerExistingSiteTestBase {

  use ContextPaneRequestTrait;
  use MailInterceptTrait;

  /**
   * The number of dispatched decline and archive events.
   */
  protected int $events = 0;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->interceptMails();
    $dispatcher = $this->container->get('event_dispatcher');
    foreach ([ProjectDeclineEvent::class, ProjectArchiveEvent::class] as $event) {
      $dispatcher->addListener($event, function (): void {
        $this->events++;
      }, 2000);
    }
  }

  /**
   * Creates a project of a new organization managed by a manager.
   */
  protected function createManagedProject(ProjectState $state, bool $prospect = FALSE, ?Creative $manager = NULL): ProjectInterface {
    $role = $prospect ? Organization::ROLE_PROSPECT : Organization::ROLE_ORGANIZATION;
    return $this->createProject($state, $this->createOrganization($manager, $role));
  }

  /**
   * Creates an assigned manager with exactly the given permissions.
   */
  protected function managerWith(array $permissions): Creative {
    $role = $this->createRole([
      'general manager access',
      'send project mails',
      'view correspondence',
      ...$permissions,
    ]);
    return $this->createCreative('creative', $role);
  }

  /**
   * Gets the POST values of the editor in the HTML.
   *
   * @return array<string, string>
   *   The values.
   */
  protected function editorPost(string $html, string $op): array {
    return ['subject' => 'S', 'body' => 'B'] + $this->hiddenValues($html, $op);
  }

  /**
   * Gets the token names of the placeholder buttons in the HTML.
   *
   * @return string[]
   *   The names in button order.
   */
  protected function placeholders(string $html): array {
    preg_match_all('/data-token="\[mail:([a-z_]+)\]"/', $html, $matches);
    return $matches[1];
  }

  /**
   * Asserts that the project is unchanged and nothing was sent.
   */
  protected function assertNothingHappened(ProjectInterface $project, string $state, bool $archived, string $label): void {
    $fresh = $this->reloadProject($project);
    $this->assertSame($state, $fresh->get('field_lifecycle')->value, $label);
    $this->assertSame($archived, $fresh->isArchived(), $label);
    $this->assertSame([], $this->entries($project), $label);
    $this->assertSame(0, $this->events, $label);
    $this->assertSame([], $this->interceptedMails, $label);
  }

  /**
   * Asserts the denied cases of an editor.
   *
   * @param array<string, array{0: \Drupal\projects\ProjectInterface, 1: \Drupal\Core\Session\AccountInterface}> $cases
   *   The projects and the accounts that may not use the editor on them.
   * @param string $pane
   *   The pane of the editor.
   * @param string $view
   *   The view of the editor.
   * @param array<string, string> $post
   *   A valid POST of the editor on another project.
   */
  protected function assertDenied(array $cases, string $pane, string $view, array $post): void {
    $query = ['type' => $pane, 'view' => $view, 'reason' => 'custom'];
    foreach ($cases as $label => [$project, $account]) {
      $this->actAs($account);
      $state = $this->reloadProject($project)->get('field_lifecycle')->value;
      $archived = $this->reloadProject($project)->isArchived();
      // The pane renders, even without any action, and offers no link.
      $this->assertStringNotContainsString('data-pane-view="' . $view . '"', $this->pane($project, ['type' => $pane]), $label);
      foreach ([NULL, $post] as $body) {
        $html = $this->pane($project, $query, $body);
        $this->assertStringNotContainsString('<form', $html, $label);
        $this->assertStringNotContainsString($view . 'd', $html, $label);
      }
      $this->assertNothingHappened($project, $state, $archived, $label);
    }
  }

  /**
   * The assigned manager sees the decline link on a prospect draft and pending.
   */
  public function testDeclineLinkShown(): void {
    $panes = $this->container->get('plugin.manager.manager_context_panes');
    foreach ([[ProjectState::Draft, TRUE], [ProjectState::Pending, FALSE]] as [$state, $prospect]) {
      $project = $this->createManagedProject($state, $prospect);
      $this->actAs($this->managerOf($project));
      $html = $this->pane($project, ['type' => 'transition']);
      $this->assertStringContainsString('data-pane-view="decline"', $html, $state->value);
      // The cheap check of the project list agrees with the built pane.
      $this->assertTrue($panes->createInstance('transition')->hasActions($project), $state->value);
    }
  }

  /**
   * Denied cases show no link, and a POST sends nothing and declines nothing.
   */
  public function testDeclineDeniedCases(): void {

    $allowed = $this->createManagedProject(ProjectState::Pending);
    $manager = $this->managerOf($allowed);
    $this->actAs($manager);
    $post = $this->editorPost($this->pane($allowed, ['type' => 'transition', 'view' => 'decline', 'reason' => 'custom']), 'Decline and send');

    $cases = [];
    foreach ([
      'non-prospect draft' => [ProjectState::Draft, FALSE],
      'open' => [ProjectState::Open, FALSE],
      'completed' => [ProjectState::Completed, FALSE],
      'declined' => [ProjectState::Declined, TRUE],
    ] as $label => [$state, $prospect]) {
      $cases[$label] = [$this->createManagedProject($state, $prospect, $manager), $manager];
    }
    $cases['manager of another organization'] = [$this->createManagedProject(ProjectState::Pending), $manager];
    $unassigned = $this->createManagedProject(ProjectState::Draft, TRUE);
    $unassigned->getOwner()->set('field_manager', NULL)->save();
    $cases['unassigned prospect'] = [$unassigned, $manager];
    $without = $this->managerWith(['restful post project:decline']);
    $project = $this->createManagedProject(ProjectState::Pending, FALSE, $without);
    $cases['without transition permission'] = [$project, $without];
    $decline = WorkflowPermissions::useTransition(ProjectLifecycle::WORKFLOW_ID, ProjectTransition::Decline->value);
    $without_rest = $this->managerWith([$decline]);
    $project = $this->createManagedProject(ProjectState::Pending, FALSE, $without_rest);
    $cases['without REST permission'] = [$project, $without_rest];

    $this->assertDenied($cases, 'transition', 'decline', $post);
  }

  /**
   * Without a reason the view opens the custom decline with all reason links.
   */
  public function testDeclineDefaultReason(): void {

    $project = $this->createManagedProject(ProjectState::Draft, TRUE);
    $this->actAs($this->managerOf($project));
    $preview = $this->container->get(ManagerMailerInterface::class)->preview($project, 'decline_custom');
    $this->assertNotNull($preview);
    $html = $this->pane($project, ['type' => 'transition', 'view' => 'decline']);

    $this->assertStringContainsString('<form', $html);
    $this->assertStringContainsString('manager-project-decline-form', $html);
    $this->assertStringContainsString(htmlspecialchars($preview->subject, ENT_QUOTES), $html);
    $this->assertStringContainsString('Decline and send', $html);
    $this->assertSame(9, preg_match_all('/data-pane-view="decline"/', $html));
    $this->assertMatchesRegularExpression('/<a[^>]*aria-current="true"[^>]*data-pane-params="reason=custom"|<a[^>]*data-pane-params="reason=custom"[^>]*aria-current="true"/', $html);
    $this->assertSame(1, preg_match_all('/aria-current="true"/', $html));
    // A decline offers the FAQ, the rules and the proposal page.
    $this->assertSame(['faq_link', 'rules_link', 'new_project_link'], $this->placeholders($html));

    // An unknown reason gives an empty pane.
    $html = $this->pane($project, ['type' => 'transition', 'view' => 'decline', 'reason' => 'unknown']);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('data-pane-view', $html);
  }

  /**
   * A form API POST of the decline editor declines and mails nothing.
   */
  public function testDeclineFormPostDoesNothing(): void {
    $project = $this->createManagedProject(ProjectState::Pending);
    $this->actAs($this->managerOf($project));
    $query = ['type' => 'transition', 'view' => 'decline', 'reason' => 'deadline'];
    $html = $this->pane($project, $query);
    $this->assertStringContainsString('data-rest-action="decline"', $html);
    $this->assertStringContainsString('data-reason="deadline"', $html);
    $this->pane($project, $query, $this->editorPost($html, 'Decline and send'));
    $this->assertNothingHappened($project, ProjectState::Pending->value, FALSE, 'decline');
  }

  /**
   * Denied cases show no link, and a POST sends nothing and archives nothing.
   */
  public function testArchiveDeniedCases(): void {

    $supervisor = $this->createSupervisor();
    $this->actAs($supervisor);
    $allowed = $this->createManagedProject(ProjectState::Open);
    $post = $this->editorPost($this->pane($allowed, ['type' => 'edit', 'view' => 'archive', 'reason' => 'custom']), 'Archive and send');

    $cases = [];
    foreach (['draft' => ProjectState::Draft, 'pending' => ProjectState::Pending, 'declined' => ProjectState::Declined] as $label => $state) {
      $cases[$label] = [$this->createManagedProject($state), $supervisor];
    }
    $archived = $this->createManagedProject(ProjectState::Open);
    $archived->setArchived()->save();
    $cases['archived'] = [$archived, $supervisor];
    // The assigned manager has no archive permission.
    $project = $this->createManagedProject(ProjectState::Open);
    $cases['manager'] = [$project, $this->managerOf($project)];

    $this->assertDenied($cases, 'edit', 'archive', $post);
  }

  /**
   * A form API POST of the archive editor archives and mails nothing.
   */
  public function testArchiveFormPostDoesNothing(): void {
    $this->actAs($this->createSupervisor());
    $project = $this->createManagedProject(ProjectState::Completed);
    $query = ['type' => 'edit', 'view' => 'archive', 'reason' => 'external'];
    $html = $this->pane($project, $query);
    $this->assertStringContainsString('außerhalb von youvo', $html);
    $this->assertStringContainsString('data-rest-action="archive"', $html);
    $this->assertStringContainsString('data-reason="external"', $html);
    $this->assertSame(3, preg_match_all('/data-pane-view="archive"/', $html));
    // An archive offers only a new draft.
    $this->assertSame(['new_draft_link'], $this->placeholders($html));
    $this->pane($project, $query, $this->editorPost($html, 'Archive and send'));
    $this->assertNothingHappened($project, ProjectState::Completed->value, FALSE, 'archive');
  }

  /**
   * The mail pane never offers or sends a decline or archive mail.
   */
  public function testMailPaneRefusesDeclineAndArchiveMails(): void {

    $draft = $this->createManagedProject(ProjectState::Draft, TRUE);
    $this->actAs($this->managerOf($draft));
    $query = ['type' => 'mail', 'view' => 'mail', 'subtype' => 'decline_custom'];
    $this->assertStringNotContainsString('<form', $this->pane($draft, $query));
    $reminder = $this->pane($draft, ['subtype' => 'reminder_draft'] + $query);
    $html = $this->pane($draft, $query, $this->editorPost($reminder, 'Send'));
    $this->assertStringNotContainsString('The mail was sent.', $html);
    $this->assertNothingHappened($draft, ProjectState::Draft->value, FALSE, 'decline');

    $open = $this->createManagedProject(ProjectState::Open);
    $this->actAs($this->createSupervisor());
    $html = $this->pane($open, ['type' => 'mail', 'view' => 'mail', 'subtype' => 'archive_custom']);
    $this->assertStringNotContainsString('<form', $html);
    $this->assertStringNotContainsString('This mail cannot be sent', $html);
  }

}
