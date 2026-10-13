<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\correspondence\Entity\Correspondence;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\Entity\LogPattern;
use Drupal\manager\Plugin\ManagerContextPane\ManagerContextPaneLogbook;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;

/**
 * Tests the merged timeline.
 *
 * @group manager
 */
class TimelineBuilderTest extends MailerKernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('log');
    LogPattern::create([
      'id' => 'project_create',
      'label' => 'Project Create',
      'tokens' => [],
      'detectable' => TRUE,
      'observable' => TRUE,
      'public' => FALSE,
      'promote' => FALSE,
      'hidden' => FALSE,
      'color' => '',
    ])->save();
  }

  /**
   * Creates a log entry of the project.
   */
  protected function createLog(Project $project, int $created): Log {
    $log = Log::create(['type' => 'project_create', 'project' => $project->id(), 'created' => $created]);
    $log->save();
    return $log;
  }

  /**
   * Creates a correspondence entry.
   */
  protected function createEntry(Project $project, string $type, int $occurred, string $subject): Correspondence {
    $entry = Correspondence::create([
      'type' => $type,
      'project' => $project->id(),
      'subject' => $subject,
      'body' => [
        'value' => $type === 'mail' ? '<p>m</p>' : 'n',
        'format' => $type === 'mail' ? 'email_html' : 'plain_text',
      ],
      'subtype' => $type === 'mail' ? 'mailer.manager.reminder_draft' : '',
      'origin' => 'manager',
      'result' => 'sent',
      'occurred' => $occurred,
    ]);
    $entry->save();
    return $entry;
  }

  /**
   * Logs, mails and notes are merged newest first.
   */
  public function testMergedOrder(): void {
    // User 1 bypasses permission checks: use it up.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $permissions = ['view correspondence', 'view observable log', 'access content'];
    $manager = $this->accountWith($project->getOwner()->getManager(), $permissions);
    $this->createLog($project, 1000);
    $this->createEntry($project, 'mail', 2000, 'M');
    $this->createEntry($project, 'note', 3000, 'N');
    $far_future = time() + 100000;
    $this->createEntry($project, 'note', $far_future, 'Newest');

    $rows = $this->container->get('manager.timeline_builder')->build($project, $manager);
    $types = array_column($rows, 'type');
    $this->assertSame('note', $types[0]);
    $this->assertSame($far_future, $rows[0]['time']);
    $this->assertContains('log', $types);
    $this->assertSame(['note', 'note', 'mail', 'log'], $types);
    $times = array_column($rows, 'time');
    $sorted = $times;
    rsort($sorted);
    $this->assertSame($sorted, $times);
    foreach ($rows as $row) {
      $this->assertArrayHasKey('build', $row);
    }
  }

  /**
   * Equal times sort by type (note, mail, log) and then by newest id first.
   */
  public function testDeterministicTieBreak(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['view correspondence']);
    $this->createLog($project, 5000);
    $mail = $this->createEntry($project, 'mail', 5000, 'mail');
    $first = $this->createEntry($project, 'note', 5000, 'first');
    $second = $this->createEntry($project, 'note', 5000, 'second');
    $rows = $this->container->get('manager.timeline_builder')->build($project, $manager);
    $order = array_map(static fn (array $row) => $row['type'] . ($row['type'] === 'log' ? '' : ':' . $row['build']['#correspondence']->id()), $rows);
    $this->assertSame(
      ['note:' . $second->id(), 'note:' . $first->id(), 'mail:' . $mail->id(), 'log'],
      $order
    );
  }

  /**
   * The assigned manager sees mails and notes of unpublished projects.
   */
  public function testManagerSeesEntriesOfUnpublishedProjects(): void {
    $this->createMailableCreative('Root');
    foreach ([ProjectState::Draft, ProjectState::Pending] as $state) {
      $project = $this->createMailableProject($state);
      $this->assertInstanceOf(Project::class, $project);
      $this->createEntry($project, 'mail', 2000, 'M');
      $this->createEntry($project, 'note', 3000, 'N');
      $manager = $this->accountWith($project->getOwner()->getManager(), ['view correspondence']);
      $types = array_column($this->container->get('manager.timeline_builder')->build($project, $manager), 'type');
      $this->assertSame(['note', 'mail'], $types, $state->name);
      $other_project = $this->createMailableProject(ProjectState::Open);
      $other = $this->accountWith($other_project->getOwner()->getManager(), ['view correspondence']);
      $this->assertSame([], $this->container->get('manager.timeline_builder')->build($project, $other), $state->name);
    }
  }

  /**
   * Mails and notes are only listed for those who may view them.
   */
  public function testHidesEntriesWithoutAccess(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $this->createLog($project, 1000);
    $this->createEntry($project, 'mail', 2000, 'M');
    $this->createEntry($project, 'note', 3000, 'N');
    $builder = $this->container->get('manager.timeline_builder');
    $permissions = ['view correspondence', 'view observable log', 'access content'];

    // The assigned manager sees all three types.
    $manager = $this->accountWith($project->getOwner()->getManager(), $permissions);
    $types = array_unique(array_column($builder->build($project, $manager), 'type'));
    sort($types);
    $this->assertSame(['log', 'mail', 'note'], $types);

    // A manager of another organization sees no mails and notes.
    $other_project = $this->createMailableProject(ProjectState::Open);
    $other_manager = $this->accountWith($other_project->getOwner()->getManager(), $permissions);
    $this->assertNotSame($manager->id(), $other_manager->id());
    $types = array_column($builder->build($project, $other_manager), 'type');
    $this->assertNotContains('mail', $types);
    $this->assertNotContains('note', $types);

    // Without the permission there are none either.
    $viewer = $this->accountWith($this->createMailableCreative('V'), ['access content']);
    $types = array_column($builder->build($project, $viewer), 'type');
    $this->assertNotContains('mail', $types);
    $this->assertNotContains('note', $types);

    // An administrator of projects sees them.
    $admin = $this->accountWith($this->createMailableCreative('Admin'), array_merge($permissions, ['administer projects']));
    $types = array_unique(array_column($builder->build($project, $admin), 'type'));
    $this->assertContains('mail', $types);
    $this->assertContains('note', $types);
  }

  /**
   * The logbook pane lists logs and entries as rows.
   */
  public function testLogbookPane(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['view correspondence', 'view observable log']);
    $this->container->get('current_user')->setAccount($manager);
    $this->createLog($project, 1000);
    $this->createEntry($project, 'note', time() + 100000, 'N');
    $pane = $this->container->get('plugin.manager.manager_context_panes')->createInstance('logbook');
    $this->assertInstanceOf(ManagerContextPaneLogbook::class, $pane);
    $build = $pane->build($project);
    $this->assertSame('logbook', $build['#type']);
    $types = array_column($build['content']['rows'], 'type');
    $this->assertSame('note', $types[0]);
    $this->assertContains('log', $types);
  }

  /**
   * Notes get edit and delete links for those who may manage them.
   */
  public function testNoteActions(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $mail = $this->createEntry($project, 'mail', 2000, 'M');
    $note = $this->createEntry($project, 'note', 3000, 'N');
    $builder = $this->container->get('manager.timeline_builder');
    $permissions = ['view correspondence', 'manage correspondence notes'];

    $manager = $this->accountWith($project->getOwner()->getManager(), $permissions);
    $rows = $builder->build($project, $manager);
    $this->assertSame(['note', 'mail'], array_column($rows, 'type'));
    $actions = $rows[0]['actions'];
    $this->assertSame(['edit', 'delete'], array_keys($actions));
    foreach (['edit' => 'manager.note_edit', 'delete' => 'manager.note_delete'] as $key => $route) {
      $link = $actions[$key];
      $this->assertSame('link', $link['#type']);
      $this->assertSame($route, $link['#url']->getRouteName());
      $this->assertSame(['correspondence' => $note->id()], $link['#url']->getRouteParameters());
      $this->assertContains('use-ajax', $link['#attributes']['class']);
      $this->assertSame('modal', $link['#attributes']['data-dialog-type']);
      $this->assertJson($link['#attributes']['data-dialog-options']);
    }
    // Mails have no links.
    $this->assertSame([], $rows[1]['actions'] ?? []);
    $this->assertNotSame($mail->id(), $note->id());

    // View permission only: the note is listed without links.
    $viewer = $this->accountWith($project->getOwner()->getManager(), ['view correspondence']);
    $rows = $builder->build($project, $viewer);
    $this->assertSame('note', $rows[0]['type']);
    $this->assertSame([], $rows[0]['actions'] ?? []);

    // A manager of another organization does not even see the note.
    $other_project = $this->createMailableProject(ProjectState::Open);
    $other = $this->accountWith($other_project->getOwner()->getManager(), $permissions);
    $this->assertSame([], $builder->build($project, $other));
  }

  /**
   * The logbook pane renders the links of notes.
   */
  public function testPaneRendersNoteActions(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $note = $this->createEntry($project, 'note', 3000, 'N');
    $this->createEntry($project, 'mail', 2000, 'M');
    $permissions = ['view correspondence', 'manage correspondence notes'];
    $manager = $this->accountWith($project->getOwner()->getManager(), $permissions);
    $this->container->get('current_user')->setAccount($manager);
    $pane = $this->container->get('plugin.manager.manager_context_panes')->createInstance('logbook');
    $build = $pane->build($project);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('/project/manage/note/' . $note->id() . '/edit', $html);
    $this->assertStringContainsString('/project/manage/note/' . $note->id() . '/delete', $html);
    $this->assertSame(1, substr_count($html, 'logbook-timeline-actions'), 'Only the note row has actions.');
    $this->assertStringContainsString('data-dialog-type="modal"', $html);
  }

}
