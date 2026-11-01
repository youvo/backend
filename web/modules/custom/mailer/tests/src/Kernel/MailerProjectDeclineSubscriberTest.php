<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\mailer\EventSubscriber\MailerProjectDeclineSubscriber;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\projects\Event\ProjectDeclineEvent;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the rejection mail sent from the decline event.
 */
#[CoversClass(MailerProjectDeclineSubscriber::class)]
#[Group('mailer')]
class MailerProjectDeclineSubscriberTest extends MailerKernelTestBase {

  /**
   * Dispatches the decline event of a project.
   */
  protected function decline(ProjectInterface $project, ?string $subject = NULL, ?string $body = NULL): void {
    $event = (new ProjectDeclineEvent($project))->setReason(ProjectDeclineReason::Deadline);
    if ($subject !== NULL && $body !== NULL) {
      $event->setMail($subject, $body)->setSender($project->getOwner()->getManager());
    }
    $this->captureMails($this->container->get(ManagerMailerInterface::class));
    $this->container->get('event_dispatcher')->dispatch($event);
  }

  /**
   * The edited mail is sent after the project was declined.
   */
  public function testEditedMail(): void {
    $project = $this->createMailableProject(ProjectState::Pending);
    $this->decline($project, 'Edited subject', 'Edited body');

    $this->assertTrue($project->lifecycle()->isDeclined());
    $this->assertCount(1, $this->capturedMails);
    $this->assertSame(['organization@example.com'], $this->capturedMails[0]['to']);
    $this->assertSame('Edited subject', $this->capturedMails[0]['subject']);
    $this->assertStringContainsString('Edited body', $this->capturedMails[0]['html']);
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertSame('mailer.manager.decline_deadline', $entries[0]->getSubtype());
    $this->assertSame('manager', $entries[0]->get('origin')->value);
  }

  /**
   * Without edited text no mail is sent.
   */
  public function testNoMailWithoutText(): void {
    $project = $this->createMailableProject(ProjectState::Pending);
    $this->decline($project);

    $this->assertTrue($project->lifecycle()->isDeclined());
    $this->assertCount(0, $this->capturedMails);
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * A failed transition sends no mail.
   */
  public function testNoMailOnFailedTransition(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    try {
      $this->decline($project, 'S', 'B');
      $this->fail('An open project cannot be declined.');
    }
    catch (\Throwable) {
      // Expected: the decline subscriber throws before the mailer runs.
    }
    $this->assertFalse($project->lifecycle()->isDeclined());
    $this->assertCount(0, $this->capturedMails);
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * A failed mail is recorded as failed, the project stays declined.
   */
  public function testFailedMail(): void {
    $project = $this->createMailableProject(ProjectState::Pending);
    $this->useFailingTransport();
    $this->decline($project, 'S', 'B');

    $this->assertTrue($project->lifecycle()->isDeclined());
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertFalse($entries[0]->isSent());
  }

}
