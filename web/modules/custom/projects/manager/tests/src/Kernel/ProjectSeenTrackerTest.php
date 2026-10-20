<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\projects\ProjectState;

/**
 * Tests the tracker of seen projects.
 *
 * @group manager
 */
class ProjectSeenTrackerTest extends ProjectSeenKernelTestBase {

  /**
   * The unfinished projects of the managed organizations are listed.
   */
  public function testManagedProjects(): void {
    $open = $this->createMailableProject(ProjectState::Open);
    $organization = $open->getOwner();
    $manager = $this->managerOf($open);
    $draft = $this->createProjectFor($organization, ProjectState::Draft);
    $pending = $this->createProjectFor($organization, ProjectState::Pending);
    $ongoing = $this->createProjectFor($organization, ProjectState::Ongoing);
    $this->createProjectFor($organization, ProjectState::Completed);
    $this->createProjectFor($organization, ProjectState::Declined);
    // A project of an organization someone else manages.
    $this->createMailableProject(ProjectState::Open);

    $ids = array_keys($this->container->get('manager.project_seen')->getManagedProjects($manager));
    sort($ids);
    $expected = array_map(static fn ($p) => (int) $p->id(), [$open, $draft, $pending, $ongoing]);
    sort($expected);
    $this->assertSame($expected, $ids);
  }

  /**
   * The project with the newest activity comes first.
   */
  public function testNewestActivityFirst(): void {
    $first = $this->createMailableProject(ProjectState::Open);
    $organization = $first->getOwner();
    $second = $this->createProjectFor($organization, ProjectState::Open);
    $manager = $this->managerOf($first);
    $this->createProjectLog($second);
    $this->createProjectLog($first);
    $ids = array_keys($this->container->get('manager.project_seen')->getManagedProjects($manager));
    $this->assertSame([(int) $first->id(), (int) $second->id()], $ids);
  }

  /**
   * A limit gives the projects with the newest activity, and no others.
   */
  public function testManagedProjectsLimit(): void {
    $first = $this->createMailableProject(ProjectState::Open);
    $organization = $first->getOwner();
    $second = $this->createProjectFor($organization, ProjectState::Open);
    $third = $this->createProjectFor($organization, ProjectState::Open);
    $manager = $this->managerOf($first);
    $this->createProjectLog($first);
    $this->createProjectLog($third);
    $this->createProjectLog($second);
    $projects = $this->container->get('manager.project_seen')->getManagedProjects($manager, 2);
    $this->assertSame([(int) $second->id(), (int) $third->id()], array_keys($projects));
  }

  /**
   * Without a row a project is new, with the logs of others counted.
   */
  public function testActivityWithoutRowIsNew(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $tracker = $this->container->get('manager.project_seen');
    $this->createProjectLog($project);
    $this->createProjectLog($project);
    // The manager's own log does not count.
    $this->createProjectLog($project, 'project_publish', $manager);
    $activity = $tracker->getActivity($manager, [$project])[$project->id()];
    $this->assertTrue($activity['is_new']);
    $this->assertSame(2, $activity['count']);
  }

  /**
   * The activity counts all observable logs of a project, the own included.
   */
  public function testActivityHasTheTotal(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->createProjectLog($project);
    $this->createProjectLog($project, 'project_publish', $manager);
    $this->createProjectLog($project, 'project_notify');
    $activity = $this->container->get('manager.project_seen')->getActivity($manager, [$project])[$project->id()];
    $this->assertSame(2, $activity['total'], 'Not observable logs are not counted.');
  }

  /**
   * The events of a project, newest first, with the new ones marked.
   */
  public function testEventsOfProject(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $tracker = $this->container->get('manager.project_seen');
    $old = $this->createProjectLog($project);
    $tracker->markSeen($manager, $project);
    $own = $this->createProjectLog($project, 'project_publish', $manager);
    $new = $this->createProjectLog($project);
    $this->createProjectLog($project, 'project_notify');
    $result = $tracker->getEvents($manager, $project);
    $this->assertSame(3, $result['total']);
    $ids = array_map(static fn (array $event) => (int) $event['log']->id(), $result['events']);
    $this->assertSame([(int) $new->id(), (int) $own->id(), (int) $old->id()], $ids);
    $flags = array_map(static fn (array $event) => $event['is_new'], $result['events']);
    $this->assertSame([TRUE, FALSE, FALSE], $flags, 'Only the log of someone else after the confirmation is new, not the own log.');
  }

  /**
   * Without a row every log of someone else is new.
   */
  public function testEventsWithoutRowAreNew(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->createProjectLog($project);
    $this->createProjectLog($project, 'project_publish', $manager);
    $result = $this->container->get('manager.project_seen')->getEvents($manager, $project);
    $flags = array_map(static fn (array $event) => $event['is_new'], $result['events']);
    $this->assertSame([FALSE, TRUE], $flags);
  }

  /**
   * The events are capped, the total tells how many there are.
   */
  public function testEventsAreCapped(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    for ($i = 0; $i < 5; $i++) {
      $this->createProjectLog($project);
    }
    $result = $this->container->get('manager.project_seen')->getEvents($manager, $project, 3);
    $this->assertCount(3, $result['events']);
    $this->assertSame(5, $result['total']);
  }

  /**
   * Another manager gets no events of a project.
   */
  public function testEventsOfProjectNotManaged(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $other = $this->managerOf($this->createMailableProject(ProjectState::Open));
    $this->createProjectLog($project);
    $result = $this->container->get('manager.project_seen')->getEvents($other, $project);
    $this->assertSame([], $result['events']);
    $this->assertSame(0, $result['total']);
  }

  /**
   * A project with no logs yet is new for a manager who never confirmed it.
   */
  public function testProjectWithoutLogsIsNew(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $this->managerOf($project);
    $activity = $this->container->get('manager.project_seen')->getActivity($manager, [$project])[$project->id()];
    $this->assertTrue($activity['is_new']);
    $this->assertSame(0, $activity['count']);
  }

  /**
   * After a confirmation only later logs count.
   */
  public function testActivityAfterConfirm(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $tracker = $this->container->get('manager.project_seen');
    $this->createProjectLog($project);
    $this->assertTrue($tracker->markSeen($manager, $project));
    $activity = $tracker->getActivity($manager, [$project])[$project->id()];
    $this->assertFalse($activity['is_new']);
    $this->assertSame(0, $activity['count']);
    $this->createProjectLog($project);
    $activity = $tracker->getActivity($manager, [$project])[$project->id()];
    $this->assertTrue($activity['is_new']);
    $this->assertSame(1, $activity['count']);
    // Confirming twice is idempotent.
    $this->assertTrue($tracker->markSeen($manager, $project));
    $this->assertTrue($tracker->markSeen($manager, $project));
    $this->assertFalse($tracker->getActivity($manager, [$project])[$project->id()]['is_new']);
    $rows = $this->container->get('database')->select('manager_project_seen')->countQuery()->execute()->fetchField();
    $this->assertSame('1', (string) $rows);
  }

  /**
   * Logs of patterns that are not observable or not enabled do not count.
   */
  public function testOnlyObservableLogsCount(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $tracker = $this->container->get('manager.project_seen');
    $tracker->markSeen($manager, $project);
    $this->createProjectLog($project, 'project_notify');
    $this->createProjectLog($project, 'project_reset');
    $activity = $tracker->getActivity($manager, [$project])[$project->id()];
    $this->assertFalse($activity['is_new']);
    $this->assertSame(0, $activity['count']);
  }

  /**
   * A project the user does not manage cannot be confirmed.
   */
  public function testMarkSeenIgnoresProjectsThatAreNotManaged(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $other_manager = $this->managerOf($this->createMailableProject(ProjectState::Open));
    $tracker = $this->container->get('manager.project_seen');
    $this->assertFalse($tracker->isManagedBy($other_manager, $project));
    $this->assertFalse($tracker->markSeen($other_manager, $project));
    $rows = $this->container->get('database')->select('manager_project_seen')->countQuery()->execute()->fetchField();
    $this->assertSame('0', (string) $rows);
  }

  /**
   * A new manager of an organization sees its projects as new.
   */
  public function testReplacedManagerSeesProjectsAsNew(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $old = $this->managerOf($project);
    $tracker = $this->container->get('manager.project_seen');
    $tracker->markSeen($old, $project);
    $new = $this->createMailableCreative('Replacement');
    $organization = $project->getOwner();
    $organization->set('field_manager', $new->id())->save();
    $this->assertSame([], $tracker->getManagedProjects($old));
    $this->assertTrue($tracker->getActivity($new, [$project])[$project->id()]['is_new']);
  }

  /**
   * Marking all projects as seen confirms every managed project.
   */
  public function testMarkAllSeen(): void {
    $first = $this->createMailableProject(ProjectState::Open);
    $second = $this->createProjectFor($first->getOwner(), ProjectState::Ongoing);
    $manager = $this->managerOf($first);
    $tracker = $this->container->get('manager.project_seen');
    $this->createProjectLog($first);
    $this->createProjectLog($second);
    $tracker->markAllSeen($manager);
    foreach ($tracker->getActivity($manager, [$first, $second]) as $activity) {
      $this->assertFalse($activity['is_new']);
    }
  }

  /**
   * The rows go with the project and with the user.
   */
  public function testRowsAreDeleted(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $this->createProjectFor($project->getOwner(), ProjectState::Open);
    $manager = $this->managerOf($project);
    $tracker = $this->container->get('manager.project_seen');
    $tracker->markAllSeen($manager);
    $count = fn () => (int) $this->container->get('database')->select('manager_project_seen')->countQuery()->execute()->fetchField();
    $this->assertSame(2, $count());
    $project->delete();
    $this->assertSame(1, $count());
    $tracker->deleteForUser($manager->id());
    $this->assertSame(0, $count());
  }

}
