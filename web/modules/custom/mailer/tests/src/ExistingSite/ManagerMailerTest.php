<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\mailer\Mailer\ManagerMailerInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the manager mailer.
 */
#[Group('mailer')]
class ManagerMailerTest extends MailerExistingSiteTestBase {

  /**
   * Gets the mailer under test.
   */
  protected function mailer(): ManagerMailerInterface {
    return $this->container->get(ManagerMailerInterface::class);
  }

  /**
   * The preview renders the policy as plain text and keeps the link token.
   *
   * The pipeline stops after the render: nothing reaches the transport (the
   * interception runs after the stop) and nothing is recorded.
   */
  public function testPreview(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $organization = $project->getOwner();
    $preview = $this->mailer()->preview($project, 'reminder_draft');
    $this->assertNotNull($preview);
    $this->assertSame([], $this->interceptedMails, 'The pipeline stops after the render.');
    $this->assertStringContainsString($project->getTitle(), $preview->subject);
    $this->assertStringContainsString($organization->getContact(), $preview->body);
    $this->assertStringContainsString($organization->getManager()->getName(), $preview->body);
    $this->assertStringContainsString('[mail:project_link]', $preview->body);
    $this->assertStringNotContainsString('<', $preview->body, 'The preview is plain text.');
    $this->assertSame([$organization->getEmail()], $preview->recipients);
    $this->assertSame([], $this->loadEntries($project), 'A preview must not be recorded.');
  }

  /**
   * A preview leaves no message behind for a user who administers mailer.
   */
  public function testPreviewAddsNoMessage(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $admin = $this->createCreative('admin');
    $admin->addRole('administrator');
    $admin->save();
    $this->assertTrue($admin->hasPermission('administer mailer'));
    $this->container->get('current_user')->setAccount($admin);
    $this->container->get('messenger')->deleteAll();
    $this->assertNotNull($this->mailer()->preview($project, 'reminder_draft'));
    $this->assertSame([], $this->container->get('messenger')->all());
  }

  /**
   * A project without recipient address has no preview and sends nothing.
   */
  public function testNoRecipient(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $project->getOwner()->set('mail', NULL)->save();
    $this->assertNull($this->mailer()->preview($project, 'reminder_draft'));
    $this->assertFalse($this->mailer()->send($project, 'reminder_draft', 'S', 'Text', $project->getOwner()->getManager()));
    $this->assertSame([], $this->interceptedMails);
    $this->assertSame([], $this->loadEntries($project));
  }

  /**
   * A subtype not offered in the state has no preview.
   *
   * Sending is guarded by the access check of the caller (ManagerMailAccess).
   */
  public function testWrongStateHasNoPreview(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertNull($this->mailer()->preview($project, 'reminder_ongoing'));
  }

  /**
   * An empty subject or body is not sent.
   */
  public function testEmptySubjectOrBodyIsNotSent(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $this->assertFalse($this->mailer()->send($project, 'reminder_draft', " \r\n ", 'Text', $manager));
    $this->assertFalse($this->mailer()->send($project, 'reminder_draft', 'Betreff', " \n ", $manager));
    $this->assertSame([], $this->interceptedMails);
    $this->assertSame([], $this->loadEntries($project));
  }

  /**
   * The edited text replaces the policy text and is recorded.
   */
  public function testSendEditedText(): void {

    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $body = "Eigener Text\n\n[mail:project_link]";
    $this->assertTrue($this->mailer()->send($project, 'reminder_draft', 'Eigener Betreff', $body, $manager));

    $this->assertCount(1, $this->interceptedMails);
    $this->assertSame([$project->getOwner()->getEmail()], $this->interceptedMails[0]['to']);
    $this->assertSame('Eigener Betreff', $this->interceptedMails[0]['subject']);
    $html = $this->interceptedMails[0]['html'];
    $this->assertStringContainsString('Eigener Text', $html);
    $this->assertStringNotContainsString('Du hast auf youvo', $html, 'The policy text must be replaced.');
    $hub = rtrim((string) $this->container->get('config.factory')->get('youvo.settings')->get('hub_url'), '/');
    $this->assertStringContainsString('<a href="' . $hub . '/draft/' . $project->uuid() . '">', $html, 'The token is resolved to the hub link and linked.');
    $this->assertStringNotContainsString('[mail:', $html);
    $this->assertStringContainsString('<html', $html, 'The mail wrapper around the body is kept.');
    // Manager mails are not marked as automatic.
    $this->assertNull($this->interceptedMails[0]['auto_submitted']);

    $entries = $this->loadEntries($project);
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
    $this->assertTrue($this->mailer()->send($project, 'reminder_draft', 'Eins', 'Erster Text', $manager));
    $this->assertTrue($this->mailer()->send($project, 'reminder_draft', 'Zwei', 'Zweiter Text', $manager));
    $this->assertStringContainsString('Zweiter Text', $this->interceptedMails[1]['html']);
    $this->assertStringNotContainsString('Erster Text', $this->interceptedMails[1]['html']);
    $preview = $this->mailer()->preview($project, 'reminder_draft');
    $this->assertStringContainsString('Du hast auf youvo', $preview->body, 'A preview after a send shows the policy text.');
  }

  /**
   * Manager text is data: Twig syntax stays literal and HTML is escaped.
   *
   * Line breaks in the subject cannot inject headers.
   */
  public function testEditedTextIsSanitisedAndNotEvaluated(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $body = "{{ 1+1 }} {% if true %}x{% endif %}\n\n<script>alert(1)</script> <b onclick=\"x()\">Hi</b> <a href=\"javascript:alert(1)\">bad</a>";
    $this->assertTrue($this->mailer()->send($project, 'reminder_draft', "Betreff\r\nBcc: evil@example.com", $body, $project->getOwner()->getManager()));
    $html = $this->interceptedMails[0]['html'];
    $this->assertStringContainsString('{{ 1+1 }}', $html);
    $this->assertStringNotContainsString('<script', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
    $this->assertStringNotContainsString('<b onclick', $html);
    $this->assertStringNotContainsString('<a href="javascript', $html);
    $this->assertSame('Betreff Bcc: evil@example.com', $this->interceptedMails[0]['subject']);
  }

  /**
   * Core token syntax in the manager text stays literal.
   */
  public function testCoreTokensStayLiteral(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $tokens = '[site:mail] [current-user:mail] [node:title]';
    $this->assertTrue($this->mailer()->send($project, 'reminder_draft', 'Betreff ' . $tokens, 'Text ' . $tokens, $project->getOwner()->getManager()));
    $this->assertStringContainsString($tokens, $this->interceptedMails[0]['html']);
    $this->assertStringContainsString($tokens, $this->interceptedMails[0]['subject']);
    $this->assertStringNotContainsString($this->siteMail() . ' [current-user:mail]', $this->interceptedMails[0]['html']);
  }

  /**
   * A very long subject is recorded truncated.
   */
  public function testLongSubjectIsRecordedTruncated(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertTrue($this->mailer()->send($project, 'reminder_draft', str_repeat('Lang ', 100), 'Text', $project->getOwner()->getManager()));
    $entries = $this->loadEntries($project);
    $this->assertCount(1, $entries);
    $subject = (string) $entries[0]->get('subject')->value;
    $this->assertLessThanOrEqual(255, mb_strlen($subject));
    $this->assertStringStartsWith('Lang Lang', $subject);
  }

}
