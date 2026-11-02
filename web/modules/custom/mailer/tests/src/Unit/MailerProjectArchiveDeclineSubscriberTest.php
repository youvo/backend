<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\mailer\EventSubscriber\MailerProjectArchiveSubscriber;
use Drupal\mailer\EventSubscriber\MailerProjectDeclineSubscriber;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\mailer\Manager\ManagerMailRegistry;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests when the archive and decline events send the edited manager mail.
 */
#[CoversClass(MailerProjectArchiveSubscriber::class)]
#[CoversClass(MailerProjectDeclineSubscriber::class)]
#[Group('mailer')]
class MailerProjectArchiveDeclineSubscriberTest extends UnitTestCase {

  /**
   * Creates a project mock in a state.
   */
  protected function project(ProjectState $state, bool $archived = FALSE): ProjectInterface {
    $lifecycle = $this->createMock(ProjectLifecycleInterface::class);
    $lifecycle->method('isDraft')->willReturn($state === ProjectState::Draft);
    $lifecycle->method('isPending')->willReturn($state === ProjectState::Pending);
    $lifecycle->method('isOpen')->willReturn($state === ProjectState::Open);
    $lifecycle->method('isOngoing')->willReturn($state === ProjectState::Ongoing);
    $lifecycle->method('isCompleted')->willReturn($state === ProjectState::Completed);
    $lifecycle->method('isDeclined')->willReturn($state === ProjectState::Declined);
    $project = $this->createMock(ProjectInterface::class);
    $project->method('lifecycle')->willReturn($lifecycle);
    $project->method('isArchived')->willReturn($archived);
    return $project;
  }

  /**
   * The archive mail is sent for archived active projects with edited text.
   */
  public function testArchive(): void {

    $sender = $this->createMock(AccountInterface::class);
    $archived = $this->project(ProjectState::Open, TRUE);
    $mailer = $this->createMock(ManagerMailerInterface::class);
    $mailer->expects($this->once())
      ->method('send')
      ->with($archived, 'archive_external', 'S', 'B', $sender);
    $subscriber = new MailerProjectArchiveSubscriber($mailer, new ManagerMailRegistry());
    $event = static fn (ProjectInterface $project) => (new ProjectArchiveEvent($project))->setReason(ProjectArchiveReason::External);

    $subscriber->mail($event($archived)->setMail('S', 'B')->setSender($sender));
    // Without the edited text or the sender.
    $subscriber->mail($event($archived));
    $subscriber->mail($event($archived)->setMail('S', 'B'));
    // The project was not archived.
    $subscriber->mail($event($this->project(ProjectState::Open))->setMail('S', 'B')->setSender($sender));
    // A state without archive mails.
    $subscriber->mail($event($this->project(ProjectState::Pending, TRUE))->setMail('S', 'B')->setSender($sender));
  }

  /**
   * The rejection mail is sent for declined projects with edited text.
   */
  public function testDecline(): void {

    $sender = $this->createMock(AccountInterface::class);
    $declined = $this->project(ProjectState::Declined);
    $mailer = $this->createMock(ManagerMailerInterface::class);
    $mailer->expects($this->once())
      ->method('send')
      ->with($declined, 'decline_deadline', 'S', 'B', $sender);
    $subscriber = new MailerProjectDeclineSubscriber($mailer);
    $event = static fn (ProjectInterface $project) => (new ProjectDeclineEvent($project))->setReason(ProjectDeclineReason::Deadline);

    $subscriber->mail($event($declined)->setMail('S', 'B')->setSender($sender));
    // Without the edited text or the sender.
    $subscriber->mail($event($declined));
    $subscriber->mail($event($declined)->setMail('S', 'B'));
    // The transition failed.
    $subscriber->mail($event($this->project(ProjectState::Pending))->setMail('S', 'B')->setSender($sender));
  }

}
