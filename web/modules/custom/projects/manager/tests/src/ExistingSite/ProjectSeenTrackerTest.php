<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\manager\ProjectSeenTracker;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the tracker of seen projects.
 */
#[Group('manager')]
class ProjectSeenTrackerTest extends ManagerExistingSiteTestBase {

  /**
   * Gets the tracker.
   */
  protected function tracker(): ProjectSeenTracker {
    return $this->container->get('manager.project_seen');
  }

  /**
   * Counts the confirmations of a user.
   */
  protected function rowsOf(int|string $uid): int {
    return (int) $this->container->get('database')->select(ProjectSeenTracker::TABLE)
      ->condition('uid', $uid)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * Gets the ids of the logs of events.
   *
   * @return int[]
   *   The log ids.
   */
  protected function eventIds(array $result): array {
    return array_map(static fn (array $event) => (int) $event['log']->id(), $result['events']);
  }

  /**
   * The unfinished projects of the managed organizations are listed.
   */
  public function testManagedProjects(): void {

    $open = $this->createProject(ProjectState::Open);
    $organization = $open->getOwner();
    $manager = $this->managerOf($open);
    $draft = $this->createProject(ProjectState::Draft, $organization);
    $pending = $this->createProject(ProjectState::Pending, $organization);
    $ongoing = $this->createProject(ProjectState::Ongoing, $organization);
    $this->createProject(ProjectState::Completed, $organization);
    $this->createProject(ProjectState::Declined, $organization);
    // A project of an organization someone else manages.
    $this->createProject(ProjectState::Open);

    $ids = array_keys($this->tracker()->getManagedProjects($manager));
    sort($ids);
    $expected = array_map(static fn ($p) => (int) $p->id(), [$open, $draft, $pending, $ongoing]);
    sort($expected);
    $this->assertSame($expected, $ids);
  }

  /**
   * The project with the newest activity comes first.
   */
  public function testNewestActivityFirst(): void {
    $first = $this->createProject(ProjectState::Open);
    $second = $this->createProject(ProjectState::Open, $first->getOwner());
    $this->createLog($second);
    $this->createLog($first);
    $ids = array_keys($this->tracker()->getManagedProjects($this->managerOf($first)));
    $this->assertSame([(int) $first->id(), (int) $second->id()], $ids);
  }

  /**
   * A limit gives the projects with the newest activity, and no others.
   */
  public function testManagedProjectsLimit(): void {
    $first = $this->createProject(ProjectState::Open);
    $second = $this->createProject(ProjectState::Open, $first->getOwner());
    $third = $this->createProject(ProjectState::Open, $first->getOwner());
    $this->createLog($first);
    $this->createLog($third);
    $this->createLog($second);
    $projects = $this->tracker()->getManagedProjects($this->managerOf($first), 2);
    $this->assertSame([(int) $second->id(), (int) $third->id()], array_keys($projects));
  }

  /**
   * Without a row a project is new, with the logs of others counted.
   */
  public function testActivityWithoutRowIsNew(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->createLog($project);
    $this->createLog($project);
    // The manager's own log does not count.
    $this->createLog($project, 'project_publish', $manager);
    $activity = $this->tracker()->getActivity($manager, [$project])[$project->id()];
    $this->assertTrue($activity['is_new']);
    $this->assertSame(2, $activity['count']);
  }

  /**
   * The activity counts all observable logs of a project, the own included.
   */
  public function testActivityHasTheTotal(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->createLog($project);
    $this->createLog($project, 'project_publish', $manager);
    $this->createLog($project, 'project_notify');
    $activity = $this->tracker()->getActivity($manager, [$project])[$project->id()];
    $this->assertSame(2, $activity['total'], 'Not observable logs are not counted.');
  }

  /**
   * The events of a project, newest first, with the new ones marked.
   */
  public function testEventsOfProject(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $old = $this->createLog($project);
    $this->tracker()->markSeen($manager, $project);
    $own = $this->createLog($project, 'project_publish', $manager);
    $new = $this->createLog($project);
    $this->createLog($project, 'project_notify');
    $result = $this->tracker()->getEvents($manager, $project);
    $this->assertSame(3, $result['total']);
    $this->assertSame([(int) $new->id(), (int) $own->id(), (int) $old->id()], $this->eventIds($result));
    $flags = array_map(static fn (array $event) => $event['is_new'], $result['events']);
    $this->assertSame([TRUE, FALSE, FALSE], $flags, 'Only the log of someone else after the confirmation is new, not the own log.');
  }

  /**
   * Without a row every log of someone else is new.
   */
  public function testEventsWithoutRowAreNew(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->createLog($project);
    $this->createLog($project, 'project_publish', $manager);
    $result = $this->tracker()->getEvents($manager, $project);
    $flags = array_map(static fn (array $event) => $event['is_new'], $result['events']);
    $this->assertSame([FALSE, TRUE], $flags);
  }

  /**
   * The events are capped, the total tells how many there are.
   */
  public function testEventsAreCapped(): void {
    $project = $this->createProject(ProjectState::Open);
    for ($i = 0; $i < 5; $i++) {
      $this->createLog($project);
    }
    $result = $this->tracker()->getEvents($this->managerOf($project), $project, 3);
    $this->assertCount(3, $result['events']);
    $this->assertSame(5, $result['total']);
  }

  /**
   * Another manager gets no events of a project.
   */
  public function testEventsOfProjectNotManaged(): void {
    $project = $this->createProject(ProjectState::Open);
    $other = $this->managerOf($this->createProject(ProjectState::Open));
    $this->createLog($project);
    $result = $this->tracker()->getEvents($other, $project);
    $this->assertSame([], $result['events']);
    $this->assertSame(0, $result['total']);
  }

  /**
   * A project with no logs yet is new for a manager who never confirmed it.
   */
  public function testProjectWithoutLogsIsNew(): void {
    $project = $this->createProject(ProjectState::Draft);
    $activity = $this->tracker()->getActivity($this->managerOf($project), [$project])[$project->id()];
    $this->assertTrue($activity['is_new']);
    $this->assertSame(0, $activity['count']);
  }

  /**
   * After a confirmation only later logs count.
   */
  public function testActivityAfterConfirm(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $tracker = $this->tracker();
    $this->createLog($project);
    $this->assertTrue($tracker->markSeen($manager, $project));
    $activity = $tracker->getActivity($manager, [$project])[$project->id()];
    $this->assertFalse($activity['is_new']);
    $this->assertSame(0, $activity['count']);

    $this->createLog($project);
    $activity = $tracker->getActivity($manager, [$project])[$project->id()];
    $this->assertTrue($activity['is_new']);
    $this->assertSame(1, $activity['count']);

    // Confirming twice is idempotent.
    $this->assertTrue($tracker->markSeen($manager, $project));
    $this->assertTrue($tracker->markSeen($manager, $project));
    $this->assertFalse($tracker->getActivity($manager, [$project])[$project->id()]['is_new']);
    $this->assertSame(1, $this->rowsOf($manager->id()));
  }

  /**
   * Logs of patterns that are not observable do not count.
   */
  public function testOnlyObservableLogsCount(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->tracker()->markSeen($manager, $project);
    $this->createLog($project, 'project_notify');
    $this->createLog($project, 'project_hide');
    $activity = $this->tracker()->getActivity($manager, [$project])[$project->id()];
    $this->assertFalse($activity['is_new']);
    $this->assertSame(0, $activity['count']);
  }

  /**
   * Archived logs neither count as activity nor show as events.
   */
  public function testArchivedLogsDoNotCount(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $tracker = $this->tracker();
    $tracker->markSeen($manager, $project);
    $active = $this->createLog($project);
    $this->createLog($project)->setArchived()->save();

    $activity = $tracker->getActivity($manager, [$project])[$project->id()];
    $this->assertSame(1, $activity['count']);
    $this->assertSame(1, $activity['total']);
    $events = $tracker->getEvents($manager, $project);
    $this->assertSame([(int) $active->id()], $this->eventIds($events));
    $this->assertSame(1, $events['total']);
  }

  /**
   * A project the user does not manage cannot be confirmed.
   */
  public function testMarkSeenIgnoresProjectsThatAreNotManaged(): void {
    $project = $this->createProject(ProjectState::Open);
    $other_manager = $this->managerOf($this->createProject(ProjectState::Open));
    $this->assertFalse($this->tracker()->isManagedBy($other_manager, $project));
    $this->assertFalse($this->tracker()->markSeen($other_manager, $project));
    $this->assertSame(0, $this->rowsOf($other_manager->id()));
  }

  /**
   * A new manager of an organization sees its projects as new.
   */
  public function testReplacedManagerSeesProjectsAsNew(): void {
    $project = $this->createProject(ProjectState::Open);
    $old = $this->managerOf($project);
    $this->tracker()->markSeen($old, $project);
    $new = $this->createManager();
    $project->getOwner()->set('field_manager', $new->id())->save();
    $this->assertSame([], $this->tracker()->getManagedProjects($old));
    $this->assertTrue($this->tracker()->getActivity($new, [$project])[$project->id()]['is_new']);
  }

  /**
   * Marking all projects as seen confirms every managed project.
   */
  public function testMarkAllSeen(): void {
    $first = $this->createProject(ProjectState::Open);
    $second = $this->createProject(ProjectState::Ongoing, $first->getOwner());
    $manager = $this->managerOf($first);
    $this->createLog($first);
    $this->createLog($second);
    $this->tracker()->markAllSeen($manager);
    $activity = $this->tracker()->getActivity($manager, [$first, $second]);
    $this->assertCount(2, $activity);
    foreach ($activity as $item) {
      $this->assertFalse($item['is_new']);
    }
  }

  /**
   * The rows go with the project and with the user.
   */
  public function testRowsAreDeleted(): void {
    $project = $this->createProject(ProjectState::Open);
    $this->createProject(ProjectState::Open, $project->getOwner());
    $manager = $this->managerOf($project);
    $this->tracker()->markAllSeen($manager);
    $this->assertSame(2, $this->rowsOf($manager->id()));
    $this->deleteCreated($project);
    $this->assertSame(1, $this->rowsOf($manager->id()));
    $this->tracker()->deleteForUser($manager->id());
    $this->assertSame(0, $this->rowsOf($manager->id()));
  }

}
