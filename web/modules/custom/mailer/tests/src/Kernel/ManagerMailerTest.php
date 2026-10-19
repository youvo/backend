<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\projects\ProjectState;

/**
 * Tests the manager mailer.
 *
 * @group mailer
 */
class ManagerMailerTest extends MailerKernelTestBase {

  /**
   * Gets the mailer under test.
   */
  protected function mailer(): ManagerMailerInterface {
    return $this->container->get(ManagerMailerInterface::class);
  }

  /**
   * The preview renders the policy and keeps the link token.
   */
  public function testPreview(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    // The capture at 950 would run if the preview did not stop at 751.
    $this->captureMails($mailer);
    $preview = $mailer->preview($project, 'reminder_draft');
    $this->assertNotNull($preview);
    $this->assertCount(0, $this->capturedMails, 'The pipeline stops after the render.');
    $this->assertStringContainsString($project->getTitle(), $preview->subject);
    $this->assertStringContainsString('Olga Org', $preview->body);
    $this->assertStringContainsString('Manu Manager', $preview->body);
    $this->assertStringContainsString('[mail:project_link]', $preview->body);
    $this->assertStringNotContainsString('<', $preview->body, 'The preview is plain text.');
    $this->assertSame(['organization@example.com'], $preview->recipients);
    $this->assertSame([], $this->loadEntries($project->id()), 'A preview must not be recorded.');
  }

  /**
   * A preview never reaches the transport and never records.
   */
  public function testPreviewNeverReachesTransport(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->useFailingTransport();
    $mailer = $this->mailer();
    \Drupal::messenger()->deleteAll();
    // The capture at 950 runs after the stop at 751 and the transport would
    // fail after it: both prove the pipeline was not continued.
    $this->captureMails($mailer);
    $preview = $mailer->preview($project, 'reminder_draft');
    $this->assertNotNull($preview);
    $this->assertCount(0, $this->capturedMails, 'The pipeline stops before the transport.');
    $this->assertSame([], \Drupal::messenger()->messagesByType('error'), 'The transport was not reached.');
    $this->assertSame([], $this->loadEntries($project->id()));
    // A real send reaches the failing transport.
    $this->assertFalse($this->mailer()->send($project, 'reminder_draft', 'S', 'Text', $project->getOwner()->getManager()));
    $this->assertCount(1, $this->loadEntries($project->id()));
  }

  /**
   * A preview leaves no message behind for a user who administers mailer.
   */
  public function testPreviewAddsNoMessage(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $admin = $this->accountWith($project->getOwner()->getManager(), ['administer mailer']);
    $this->container->get('current_user')->setAccount($admin);
    \Drupal::messenger()->deleteAll();
    $preview = $this->mailer()->preview($project, 'reminder_draft');
    $this->assertNotNull($preview);
    $this->assertNotSame('', $preview->subject);
    $this->assertNotSame('', $preview->body);
    $this->assertSame([], \Drupal::messenger()->all());
  }

  /**
   * An empty subject or body is not sent.
   */
  public function testEmptySubjectOrBodyIsNotSent(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertFalse($mailer->send($project, 'reminder_draft', " \r\n ", 'Text', $manager));
    $this->assertFalse($mailer->send($project, 'reminder_draft', 'Betreff', " \n ", $manager));
    $this->assertCount(0, $this->capturedMails);
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * A project without recipient address has no preview and sends nothing.
   */
  public function testNoRecipient(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $project->getOwner()->set('mail', '')->save();
    $this->assertNull($this->mailer()->preview($project, 'reminder_draft'));
    $this->assertFalse($this->mailer()->send($project, 'reminder_draft', 'S', 'Text', $project->getOwner()->getManager()));
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * A subtype not offered in the state has no preview and is not sent.
   */
  public function testWrongState(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    $this->assertNull($mailer->preview($project, 'reminder_ongoing'));
    $this->captureMails($mailer);
    $this->assertFalse($mailer->send($project, 'reminder_ongoing', 'S', 'Text', $project->getOwner()->getManager()));
    $this->assertCount(0, $this->capturedMails);
    $this->assertSame([], $this->loadEntries($project->id()));
  }

  /**
   * The edited text replaces the policy text and is recorded.
   */
  public function testSendEditedText(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $body = "Eigener Text\n\n[mail:project_link]";
    $this->assertTrue($mailer->send($project, 'reminder_draft', 'Eigener Betreff', $body, $manager));

    $this->assertCount(1, $this->capturedMails);
    $this->assertSame(['organization@example.com'], $this->capturedMails[0]['to']);
    $this->assertSame('Eigener Betreff', $this->capturedMails[0]['subject']);
    $html = $this->capturedMails[0]['html'];
    $this->assertStringContainsString('Eigener Text', $html);
    $this->assertStringNotContainsString('Du hast auf youvo', $html, 'The policy text must be replaced.');
    $this->assertStringContainsString('<a href="https://hub.youvo.org/draft/' . $project->uuid() . '">', $html, 'The token is resolved to the hub link and linked.');
    $this->assertStringNotContainsString('[mail:', $html);
    $this->assertStringContainsString('<html', $html, 'The mail wrapper around the body is kept.');
    $this->assertStringContainsString('email-sub-type-manager', $html);
    // Manager mails are not marked as automatic.
    $this->assertNull($this->capturedMails[0]['auto_submitted']);

    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertSame('manager', $entries[0]->get('origin')->value);
    $this->assertSame((int) $manager->id(), (int) $entries[0]->get('sender')->target_id);
    $this->assertSame('mailer.manager.reminder_draft', $entries[0]->getSubtype());
    $this->assertStringNotContainsString('<!doctype', strtolower($entries[0]->get('body')->value));
    $this->assertStringContainsString('Eigener Text', $entries[0]->get('body')->value);
  }

  /**
   * The override applies to one email only: the next send uses its own text.
   */
  public function testOverrideDoesNotLeak(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($mailer->send($project, 'reminder_draft', 'Eins', 'Erster Text', $manager));
    $this->captureMails($mailer);
    $this->assertTrue($mailer->send($project, 'reminder_draft', 'Zwei', 'Zweiter Text', $manager));
    $this->assertStringContainsString('Zweiter Text', $this->capturedMails[1]['html']);
    $this->assertStringNotContainsString('Erster Text', $this->capturedMails[1]['html']);
    $preview = $mailer->preview($project, 'reminder_draft');
    $this->assertStringContainsString('Du hast auf youvo', $preview->body, 'A preview after a send shows the policy text.');
  }

  /**
   * Manager text is data: Twig syntax stays literal and HTML is escaped.
   */
  public function testEditedTextIsSanitisedAndNotEvaluated(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $body = "{{ 1+1 }} {% if true %}x{% endif %}\n\n<script>alert(1)</script> <b onclick=\"x()\">Hi</b> <a href=\"javascript:alert(1)\">bad</a>";
    $this->assertTrue($mailer->send($project, 'reminder_draft', "Betreff\r\nBcc: evil@example.com", $body, $project->getOwner()->getManager()));
    $html = $this->capturedMails[0]['html'];
    $this->assertStringContainsString('{{ 1+1 }}', $html);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
    $this->assertStringNotContainsString('<b onclick', $html);
    $this->assertStringNotContainsString('<a href="javascript', $html);
    $this->assertStringNotContainsString("\n", $this->capturedMails[0]['subject']);
    $this->assertSame('Betreff Bcc: evil@example.com', $this->capturedMails[0]['subject']);
  }

  /**
   * Core token syntax in the manager text stays literal.
   */
  public function testCoreTokensStayLiteral(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $tokens = '[site:mail] [current-user:mail] [node:title]';
    $this->assertTrue($mailer->send($project, 'reminder_draft', 'Betreff ' . $tokens, 'Text ' . $tokens, $project->getOwner()->getManager()));
    $html = $this->capturedMails[0]['html'];
    $this->assertStringContainsString($tokens, $html);
    $this->assertStringContainsString($tokens, $this->capturedMails[0]['subject']);
    $this->assertStringNotContainsString('noreply@youvo.org Text', $html);
  }

  /**
   * A very long subject is recorded truncated.
   */
  public function testLongSubjectIsRecordedTruncated(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertTrue($this->mailer()->send($project, 'reminder_draft', str_repeat('Lang ', 100), 'Text', $project->getOwner()->getManager()));
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertLessThanOrEqual(255, mb_strlen($entries[0]->get('subject')->value));
  }

  /**
   * A failed send reports FALSE and records a failed manager entry.
   */
  public function testFailedSendIsRecorded(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->useFailingTransport();
    $this->assertFalse($this->mailer()->send($project, 'reminder_draft', 'S', 'Text', $project->getOwner()->getManager()));
    $entries = array_values($this->loadEntries($project->id()));
    $this->assertCount(1, $entries);
    $this->assertFalse($entries[0]->isSent());
    $this->assertSame('manager', $entries[0]->get('origin')->value);
  }

}
