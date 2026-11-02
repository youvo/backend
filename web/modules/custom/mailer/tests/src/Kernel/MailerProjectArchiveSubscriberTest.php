<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\mailer\EventSubscriber\MailerProjectArchiveSubscriber;
use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\projects\Event\ProjectArchiveEvent;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the mail sent from the archive event.
 */
#[CoversClass(MailerProjectArchiveSubscriber::class)]
#[Group('mailer')]
class MailerProjectArchiveSubscriberTest extends MailerKernelTestBase {

  /**
   * Dispatches the archive event of a project.
   */
  protected function archive(ProjectInterface $project, ?string $subject = NULL, ?string $body = NULL): void {
    $event = (new ProjectArchiveEvent($project))->setReason(ProjectArchiveReason::External);
    if ($subject !== NULL && $body !== NULL) {
      $event->setMail($subject, $body)->setSender($project->getOwner()->getManager());
    }
    $this->captureMails($this->container->get(ManagerMailerInterface::class));
    $this->container->get('event_dispatcher')->dispatch($event);
  }

  /**
   * The edited mail is sent after the project was archived.
   */
  public function testEditedMail(): void {

    $project = $this->createMailableProject(ProjectState::Open);
    $this->archive($project, 'Edited subject', 'Edited body');

    $this->assertTrue($project->isArchived());
    $this->assertCount(1, $this->capturedMails);
    $this->assertSame(['organization@example.com'], $this->capturedMails[0]['to']);
    $this->assertSame('Edited subject', $this->capturedMails[0]['subject']);
    $this->assertStringContainsString('Edited body', $this->capturedMails[0]['html']);
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertSame('mailer.manager.archive_external', $entries[0]->getSubtype());
    $this->assertSame('manager', $entries[0]->get('origin')->value);
  }

  /**
   * Without edited text no mail is sent.
   */
  public function testNoMailWithoutText(): void {

    $project = $this->createMailableProject(ProjectState::Open);
    $this->archive($project);

    $this->assertTrue($project->isArchived());
    $this->assertCount(0, $this->capturedMails);
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * Archiving a pending project sends no mail, even with edited text.
   */
  public function testNoMailForOtherStates(): void {

    $project = $this->createMailableProject(ProjectState::Pending);
    $this->archive($project, 'S', 'B');

    $this->assertTrue($project->isArchived());
    $this->assertCount(0, $this->capturedMails);
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * A failed mail is recorded as failed, the project stays archived.
   */
  public function testFailedMail(): void {

    $project = $this->createMailableProject(ProjectState::Open);
    $this->useFailingTransport();
    $this->archive($project, 'S', 'B');

    $this->assertTrue($project->isArchived());
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertFalse($entries[0]->isSent());
  }

}
