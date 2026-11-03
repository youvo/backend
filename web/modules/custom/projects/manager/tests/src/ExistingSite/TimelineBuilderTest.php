<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\manager\Timeline\TimelineBuilder;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the merged timeline of logs, mails and notes.
 */
#[Group('manager')]
class TimelineBuilderTest extends ManagerExistingSiteTestBase {

  /**
   * Gets the timeline builder.
   */
  protected function builder(): TimelineBuilder {
    return $this->container->get('manager.timeline_builder');
  }

  /**
   * Gets the row types of the timeline of a project for an account.
   *
   * @return string[]
   *   The types, newest first.
   */
  protected function types(ProjectInterface $project, AccountInterface $account): array {
    return array_column($this->builder()->build($project, $account), 'type');
  }

  /**
   * Renders the logbook pane of a project for the current user.
   */
  protected function renderPane(ProjectInterface $project): string {
    $build = $this->container->get('plugin.manager.manager_context_panes')->createInstance('logbook')->build($project);
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Logs, mails and notes are merged newest first.
   */
  public function testMergedOrder(): void {
    $project = $this->createProject(ProjectState::Open);
    $this->createLog($project, 'project_create', NULL, 1000);
    $this->createMailEntry($project, 2000);
    $this->createNote($project, 'N', 'n', 3000);
    $far_future = time() + 100000;
    $this->createNote($project, 'Newest', 'n', $far_future);

    $rows = $this->builder()->build($project, $this->managerOf($project));
    $this->assertSame(['note', 'note', 'mail', 'log'], array_column($rows, 'type'));
    $this->assertSame([$far_future, 3000, 2000, 1000], array_column($rows, 'time'));
    foreach ($rows as $row) {
      $this->assertArrayHasKey('build', $row);
    }
  }

  /**
   * Equal times sort by type (note, mail, log) and then by newest id first.
   */
  public function testDeterministicTieBreak(): void {
    $project = $this->createProject(ProjectState::Open);
    $this->createLog($project, 'project_create', NULL, 5000);
    $mail = $this->createMailEntry($project, 5000);
    $first = $this->createNote($project, 'first', 'n', 5000);
    $second = $this->createNote($project, 'second', 'n', 5000);
    $rows = $this->builder()->build($project, $this->managerOf($project));
    $order = array_map(static fn (array $row) => $row['type'] . ($row['type'] === 'log' ? '' : ':' . $row['id']), $rows);
    $this->assertSame(['note:' . $second->id(), 'note:' . $first->id(), 'mail:' . $mail->id(), 'log'], $order);
  }

  /**
   * Logs follow the log access: archived logs need the permission.
   */
  public function testLogsFollowLogAccess(): void {

    $project = $this->createProject(ProjectState::Open);
    $active = $this->createLog($project, 'project_create', NULL, 1000);
    $archived = $this->createLog($project, 'project_create', NULL, 2000)->setArchived();
    $archived->save();
    $logs = fn (AccountInterface $account): array => array_column(
      array_filter($this->builder()->build($project, $account), static fn (array $row): bool => $row['type'] === 'log'),
      'id',
    );

    // The manager of the organization sees the active log only.
    $this->assertSame([(int) $active->id()], $logs($this->managerOf($project)));
    // Without a log permission, no log is shown.
    $this->assertSame([], $logs($this->createCreative()));
    // Supervisors see the archived log as well.
    $this->assertSame([(int) $archived->id(), (int) $active->id()], $logs($this->createSupervisor()));
  }

  /**
   * The assigned manager sees mails and notes of unpublished projects.
   */
  public function testManagerSeesEntriesOfUnpublishedProjects(): void {
    foreach ([ProjectState::Draft, ProjectState::Pending] as $state) {
      $project = $this->createProject($state);
      $this->createMailEntry($project, 2000);
      $this->createNote($project, 'N', 'n', 3000);
      $this->assertSame(['note', 'mail'], $this->types($project, $this->managerOf($project)), $state->value);
      $other = $this->managerOf($this->createProject(ProjectState::Open));
      $this->assertSame([], $this->types($project, $other), $state->value);
    }
  }

  /**
   * Mails and notes are only listed for those who may view them.
   */
  public function testHidesEntriesWithoutAccess(): void {

    $project = $this->createProject(ProjectState::Open);
    $this->createLog($project, 'project_create', NULL, 1000);
    $this->createMailEntry($project, 2000);
    $this->createNote($project, 'N', 'n', 3000);

    // The assigned manager sees all three types.
    $this->assertSame(['note', 'mail', 'log'], $this->types($project, $this->managerOf($project)));

    // A manager of another organization and a creative see no mails and notes.
    $other = $this->managerOf($this->createProject(ProjectState::Open));
    foreach (['manager of another organization' => $other, 'creative' => $this->createCreative()] as $label => $account) {
      $types = $this->types($project, $account);
      $this->assertNotContains('mail', $types, $label);
      $this->assertNotContains('note', $types, $label);
    }

    // A supervisor sees them.
    $this->assertSame(['note', 'mail', 'log'], $this->types($project, $this->createSupervisor()));
  }

  /**
   * The rendered timeline is cached and invalidated by changes of the project.
   */
  public function testTimelineIsRenderCached(): void {

    $project = $this->createProject(ProjectState::Open);
    $this->actAs($this->managerOf($project));
    $note = $this->createNote($project, 'First', 'n', 3000);
    $this->assertStringContainsString('First', $this->renderPane($project));

    // A change that bypasses the entity hooks stays invisible: the second
    // render comes from the cache.
    $storage = $this->container->get('entity_type.manager')->getStorage('correspondence');
    $this->assertInstanceOf(SqlContentEntityStorage::class, $storage);
    $this->container->get('database')->update($storage->getBaseTable())
      ->fields(['subject' => 'Sneaky'])
      ->condition('id', $note->id())
      ->execute();
    $storage->resetCache();
    $html = $this->renderPane($project);
    $this->assertStringContainsString('First', $html);
    $this->assertStringNotContainsString('Sneaky', $html);

    // A new entry invalidates the timeline of the project.
    $this->createNote($project, 'Second', 'n', 4000);
    $html = $this->renderPane($project);
    $this->assertStringContainsString('Second', $html);
    $this->assertStringContainsString('Sneaky', $html);

    // So does a new log.
    $this->createLog($project, 'project_create', NULL, 5000);
    $this->assertSame(substr_count($html, 'data-row-key') + 1, substr_count($this->renderPane($project), 'data-row-key'));

    // Deleting an entry removes it.
    $note->delete();
    $this->assertStringNotContainsString('Sneaky', $this->renderPane($project));
  }

  /**
   * The cached timeline is kept per user.
   */
  public function testTimelineCacheVariesByUser(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->createNote($project, 'Visible', 'n', 3000);
    $this->actAs($manager);
    $this->assertStringContainsString('Visible', $this->renderPane($project));
    // Another user without the permission must not get the cached rows.
    $this->actAs($this->createCreative());
    $this->assertStringNotContainsString('Visible', $this->renderPane($project));
    // And the manager still gets theirs.
    $this->actAs($manager);
    $this->assertStringContainsString('Visible', $this->renderPane($project));
  }

}
