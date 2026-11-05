<?php

namespace Drupal\Tests\logbook\ExistingSite;

use Drupal\correspondence\Entity\Correspondence;
use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Entity\Log;
use Drupal\logbook\LogInterface;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\Event\ProjectFollowEvent;
use Drupal\projects\Event\ProjectRemindEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\youvo\Traits\MailInterceptTrait;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the logs of manager reminders and followed mail links.
 *
 * Doesn't extend ProjectResourceTestBase, which removes the logbook listeners.
 */
#[Group('logbook')]
class LogbookMailDoorTest extends ExistingSiteBase {

  use MailInterceptTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->interceptMails();
  }

  /**
   * Creates a project of an organization with a manager.
   */
  protected function createProject(ProjectState $state): ProjectInterface {

    $manager = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);

    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.org',
      'field_manager' => $manager,
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);

    $project = Project::create([
      'uid' => $organization->id(),
      'title' => $this->randomMachineName(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());

    return $project;
  }

  /**
   * Gets the logs of a pattern of a project.
   *
   * @return \Drupal\logbook\LogInterface[]
   *   The logs.
   */
  protected function logs(ProjectInterface $project, string $type): array {
    $ids = $this->container->get('entity_type.manager')->getStorage('log')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $type)
      ->condition('project', $project->id())
      ->execute();
    $logs = Log::loadMultiple($ids);
    array_walk($logs, fn (LogInterface $log) => $this->markEntityForCleanup($log));
    return array_values($logs);
  }

  /**
   * Tests that a reminder of the manager writes a log.
   */
  public function testRemindEventWritesLog(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();
    $uuid = $this->container->get('uuid')->generate();
    $this->setCurrentUser($manager);

    $this->container->get('event_dispatcher')->dispatch(new ProjectRemindEvent($project, 'mailer.manager.reminder_open', $uuid));

    $logs = $this->logs($project, 'project_remind');
    $this->assertCount(1, $logs);
    $this->assertSame((int) $manager->id(), (int) $logs[0]->getOwnerId());
    $this->assertSame((int) $project->getOwner()->id(), (int) $logs[0]->getOrganization()?->id());
    $this->assertSame((int) $manager->id(), (int) $logs[0]->getManager()?->id());
    $this->assertSame(['subtype' => 'mailer.manager.reminder_open', 'correspondence' => $uuid], $logs[0]->getMisc());
  }

  /**
   * Tests that a sent reminder writes a log with its correspondence.
   */
  public function testReminderSendWritesRemindLog(): void {

    $project = $this->createProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $this->setCurrentUser($manager);

    $this->assertTrue($this->container->get(ManagerMailerInterface::class)->send($project, 'reminder_draft', 'Betreff', 'Text [mail:project_link]', $manager));

    $ids = $this->container->get('entity_type.manager')->getStorage('correspondence')->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project->id())
      ->execute();
    $this->assertCount(1, $ids);
    $entry = Correspondence::load(reset($ids));
    $this->markEntityForCleanup($entry);
    $logs = $this->logs($project, 'project_remind');
    $this->assertCount(1, $logs);
    $this->assertSame((int) $manager->id(), (int) $logs[0]->getOwnerId());
    $this->assertSame(['subtype' => 'mailer.manager.reminder_draft', 'correspondence' => $entry->uuid()], $logs[0]->getMisc());
  }

  /**
   * Tests that a followed link of the organization writes a log.
   */
  public function testFollowEventWritesLog(): void {

    $project = $this->createProject(ProjectState::Open);
    $organization = $project->getOwner();
    $uuid = $this->container->get('uuid')->generate();
    $this->setCurrentUser($this->container->get('entity_type.manager')->getStorage('user')->load(0));

    $this->container->get('event_dispatcher')->dispatch(new ProjectFollowEvent($project, $organization, 'mailer.manager.reminder_open', $uuid, 'applicants'));

    $logs = $this->logs($project, 'project_follow');
    $this->assertCount(1, $logs);
    $this->assertSame((int) $organization->id(), (int) $logs[0]->getOwnerId());
    $this->assertSame((int) $organization->id(), (int) $logs[0]->getOrganization()?->id());
    $this->assertSame((int) $organization->getManager()->id(), (int) $logs[0]->getManager()?->id());
    $this->assertSame([], $logs[0]->getCreatives());
    $this->assertSame([
      'subtype' => 'mailer.manager.reminder_open',
      'correspondence' => $uuid,
      'target' => 'applicants',
    ], $logs[0]->getMisc());
  }

  /**
   * Tests that a followed link of a creative names the creative.
   */
  public function testFollowByCreativeSetsCreatives(): void {

    $project = $this->createProject(ProjectState::Ongoing);
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'status' => 1,
    ]);
    $creative->addRole('creative');
    $creative->save();
    $this->markEntityForCleanup($creative);
    $project->appendParticipant($creative)->save();
    $uuid = $this->container->get('uuid')->generate();

    $this->container->get('event_dispatcher')->dispatch(new ProjectFollowEvent($project, $creative, 'mailer.manager.reminder_open', $uuid, 'feedback'));

    $logs = $this->logs($project, 'project_follow');
    $this->assertCount(1, $logs);
    $this->assertSame((int) $creative->id(), (int) $logs[0]->getOwnerId());
    $this->assertSame([(int) $creative->id()], array_values(array_map(static fn ($c) => (int) $c->id(), $logs[0]->getCreatives())));
  }

  /**
   * Tests that the log names the subject of the mail, or the subtype.
   */
  public function testMailTokenNamesTheSubject(): void {

    $project = $this->createProject(ProjectState::Open);
    $entry = Correspondence::create([
      'type' => 'mail',
      'project' => $project->id(),
      'subject' => 'Erinnerung: Bewerbungen',
      'body' => ['value' => '<p>m</p>', 'format' => 'email_html'],
      'subtype' => 'mailer.manager.reminder_open',
      'origin' => 'manager',
      'result' => 'sent',
      'occurred' => 2000,
    ]);
    $entry->save();
    $this->markEntityForCleanup($entry);

    $this->container->get('event_dispatcher')->dispatch(new ProjectFollowEvent($project, $project->getOwner(), 'mailer.manager.reminder_open', $entry->uuid(), 'applicants'));
    $this->container->get('event_dispatcher')->dispatch(new ProjectFollowEvent($project, $project->getOwner(), 'mailer.manager.reminder_open', $this->container->get('uuid')->generate(), 'applicants'));

    $markups = array_map(static fn (LogInterface $log) => (string) $log->get('markup')->value, $this->logs($project, 'project_follow'));
    $this->assertCount(2, $markups);
    $this->assertCount(1, array_filter($markups, static fn (string $m) => str_contains($m, '„Erinnerung: Bewerbungen“')));
    $this->assertCount(1, array_filter($markups, static fn (string $m) => str_contains($m, '„mailer.manager.reminder_open“')));
    foreach ($markups as $markup) {
      $this->assertStringNotContainsString('%Mail', $markup);
    }
  }

  /**
   * Tests that a followed link is new activity for the manager.
   */
  public function testFollowIsNewForManager(): void {

    $project = $this->createProject(ProjectState::Open);
    $manager = $project->getOwner()->getManager();
    $tracker = $this->container->get('manager.project_seen');
    $tracker->markSeen($manager, $project);
    $this->assertFalse($tracker->getActivity($manager, [$project])[(int) $project->id()]['is_new']);

    $this->container->get('event_dispatcher')->dispatch(new ProjectFollowEvent($project, $project->getOwner(), 'mailer.manager.reminder_open', $this->container->get('uuid')->generate(), 'applicants'));
    $this->logs($project, 'project_follow');

    $this->assertTrue($tracker->getActivity($manager, [$project])[(int) $project->id()]['is_new']);
  }

}
