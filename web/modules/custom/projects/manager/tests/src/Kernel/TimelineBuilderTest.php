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
   * Equal times keep the newest entry first.
   */
  public function testStableForEqualTimes(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['view correspondence']);
    $first = $this->createEntry($project, 'note', 5000, 'first');
    $second = $this->createEntry($project, 'note', 5000, 'second');
    $rows = $this->container->get('manager.timeline_builder')->build($project, $manager);
    $notes = array_values(array_filter($rows, static fn (array $row) => $row['type'] === 'note'));
    $this->assertSame($second->id(), $notes[0]['build']['#correspondence']->id());
    $this->assertSame($first->id(), $notes[1]['build']['#correspondence']->id());
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

}
