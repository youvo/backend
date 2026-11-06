<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\mailer_door\DoorTarget;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\symfony_mailer\EmailInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project transition mailer.
 *
 * The behaviors of the mailer base class (recording, failed sends, missing
 * recipients, headers and fallbacks) are tested here once, with the publish
 * mail as the representative.
 */
#[Group('mailer')]
class ProjectTransitionMailerTest extends MailerExistingSiteTestBase {

  /**
   * Gets the mailer under test.
   */
  protected function mailer(): ProjectTransitionMailerInterface {
    return $this->container->get(ProjectTransitionMailerInterface::class);
  }

  /**
   * Captures the address and auto-reply headers of the next mail.
   *
   * @return array{from: string[], from_name: string[], reply_to: string[], reply_to_name: string[], auto_response_suppress: ?string}
   *   The headers of the next mail of the mailer, filled once it is rendered.
   */
  protected function &captureHeaders(): array {
    $headers = [];
    $this->mailer()->addCallback(static function (EmailInterface $email) use (&$headers): void {
      $headers = [
        'from' => array_map(static fn ($a) => $a->getEmail(), $email->getFrom()),
        'from_name' => array_map(static fn ($a) => $a->getDisplayName(), $email->getFrom()),
        'reply_to' => array_map(static fn ($a) => $a->getEmail(), $email->getReplyTo()),
        'reply_to_name' => array_map(static fn ($a) => $a->getDisplayName(), $email->getReplyTo()),
        'auto_response_suppress' => $email->getHeaders()->get('X-Auto-Response-Suppress')?->getBodyAsString(),
      ];
    }, EmailInterface::PHASE_POST_RENDER, 951, 'header_capture');
    return $headers;
  }

  /**
   * The publish event sends the policy mail and records it.
   */
  public function testPublish(): void {

    $project = $this->createMailableProject(ProjectState::Pending);
    $this->container->get('event_dispatcher')->dispatch(new ProjectPublishEvent($project));

    $this->assertCount(1, $this->interceptedMails);
    $this->assertSame([$project->getOwner()->getEmail()], $this->interceptedMails[0]['to']);
    $this->assertStringContainsString($project->getTitle(), $this->interceptedMails[0]['subject']);
    $this->assertStringContainsString('<p>', $this->interceptedMails[0]['html']);

    $entries = $this->loadEntries($project);
    $this->assertCount(1, $entries);
    $this->assertSame('mailer.project_transition.publish_organization', $entries[0]->getSubtype());
    $this->assertSame('automatic', $entries[0]->get('origin')->value);
    $this->assertSame([$project->getOwner()->getEmail()], json_decode($entries[0]->get('recipients')->value, TRUE));
    $this->assertTrue($entries[0]->isSent());
  }

  /**
   * The mail names the manager as sender and has them as reply-to.
   */
  public function testHeaders(): void {

    $project = $this->createMailableProject();
    $manager = $project->getOwner()->getManager();
    $headers = &$this->captureHeaders();
    $this->assertTrue($this->mailer()->publish($project));

    $this->assertSame([$this->siteMail()], $headers['from']);
    $this->assertSame([$manager->getName() . ' | ' . $this->siteName()], $headers['from_name']);
    $this->assertSame([$manager->getEmail()], $headers['reply_to']);
    $this->assertSame([$manager->getName()], $headers['reply_to_name']);
    // Automatic mails suppress out-of-office replies.
    $this->assertSame('auto-generated', $this->interceptedMails[0]['auto_submitted']);
    $this->assertSame('OOF, AutoReply', $headers['auto_response_suppress']);
  }

  /**
   * A manager without a name leaves the sender name to the site.
   */
  public function testManagerWithoutName(): void {
    $project = $this->createMailableProject();
    $manager = $project->getOwner()->getManager();
    $manager->set('field_name', NULL)->save();
    $headers = &$this->captureHeaders();
    $this->assertTrue($this->mailer()->publish($project));
    $this->assertSame([$this->siteName()], $headers['from_name']);
    $this->assertSame([$manager->getEmail()], $headers['reply_to']);
    $this->assertSame([''], $headers['reply_to_name']);
  }

  /**
   * Without a manager the site is the sender and reply-to, the team signs.
   */
  public function testWithoutManager(): void {

    $project = $this->createMailableProject();
    $project->getOwner()->set('field_manager', NULL)->save();
    $headers = &$this->captureHeaders();
    $this->assertTrue($this->mailer()->publish($project));

    $this->assertSame([$this->siteMail()], $headers['from']);
    $this->assertSame([$this->siteName()], $headers['from_name']);
    $this->assertSame([$this->siteMail()], $headers['reply_to']);
    $this->assertSame([$this->siteName()], $headers['reply_to_name']);
    $this->assertStringContainsString('Dein youvo-Team', $this->interceptedMails[0]['html']);
  }

  /**
   * A failing transport returns FALSE, does not throw and is recorded.
   */
  public function testFailedSendIsRecorded(): void {
    $project = $this->createMailableProject();
    $this->failMails();
    $this->assertFalse($this->mailer()->publish($project));
    $entries = $this->loadEntries($project);
    $this->assertCount(1, $entries);
    $this->assertFalse($entries[0]->isSent());
  }

  /**
   * An organization without mail address ends in FALSE, nothing is sent.
   */
  public function testMissingRecipient(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $project->getOwner()->set('mail', NULL)->save();
    $project->getOwner()->getManager()->set('mail', NULL)->save();
    $this->assertFalse($this->mailer()->publish($project));
    $this->assertFalse($this->mailer()->submit($project));
    $this->assertSame([], $this->interceptedMails);
    $this->assertSame([], $this->loadEntries($project));
  }

  /**
   * An owner that is no organization does not make the mailer throw.
   */
  public function testOwnerWithoutOrganizationDoesNotThrow(): void {
    $owner = $this->createCreative('owner', '');
    $project = $this->createMock(ProjectInterface::class);
    $project->method('getOwner')->willReturn($owner);
    $project->method('getTitle')->willReturn('Title');
    $project->method('id')->willReturn(0);
    $this->assertFalse($this->mailer()->publish($project));
    $this->assertFalse($this->mailer()->submit($project));
    $this->assertSame([], $this->interceptedMails);
  }

  /**
   * Entity data is escaped in the body, plain in the subject, never evaluated.
   */
  public function testEntityDataIsEscapedAndNotEvaluated(): void {
    $project = $this->createMailableProject();
    $project->set('title', 'A & <b>x</b> {{ 1+1 }}')->save();
    $this->assertTrue($this->mailer()->publish($project));
    $html = $this->interceptedMails[0]['html'];
    $this->assertStringContainsString('A &amp; &lt;b&gt;x&lt;/b&gt;', $html);
    $this->assertStringNotContainsString('<b>x</b>', $html);
    $this->assertStringContainsString('{{ 1+1 }}', $html);
    $subject = $this->interceptedMails[0]['subject'];
    $this->assertStringContainsString('A & <b>x</b> {{ 1+1 }}', $subject);
    $this->assertStringNotContainsString('&amp;', $subject);
  }

  /**
   * The mailer skips mails while the installer is running.
   *
   * A skipped mail counts as handled, but nothing is sent or recorded.
   */
  public function testInstallerSkipsMail(): void {

    $project = $this->createMailableProject();
    $GLOBALS['install_state'] = [];
    try {
      $result = $this->mailer()->publish($project);
    }
    finally {
      unset($GLOBALS['install_state']);
    }

    $this->assertTrue($result);
    $this->assertSame([], $this->interceptedMails);
    $this->assertSame([], $this->loadEntries($project));
  }

  /**
   * The submit event mails the manager and the organization.
   *
   * The mail to the manager comes from and replies to the site.
   */
  public function testSubmit(): void {

    $project = $this->createMailableProject(ProjectState::Draft);
    $manager = $project->getOwner()->getManager();
    $headers = &$this->captureHeaders();
    $this->container->get('event_dispatcher')->dispatch(new ProjectSubmitEvent($project));

    $this->assertSame([$this->siteMail()], $headers['from']);
    $this->assertSame([$this->siteMail()], $headers['reply_to']);
    $entries = $this->loadEntriesBySubtype($project);
    $this->assertSame(['mailer.project_transition.submit_manager', 'mailer.project_transition.submit_organization'], array_keys($entries));
    $to_manager = $entries['mailer.project_transition.submit_manager'][0];
    $this->assertSame([$manager->getEmail()], json_decode($to_manager->get('recipients')->value, TRUE));
    $this->assertStringContainsString($project->getTitle(), $to_manager->get('subject')->value);
    $to_organization = $entries['mailer.project_transition.submit_organization'][0];
    $this->assertSame([$project->getOwner()->getEmail()], json_decode($to_organization->get('recipients')->value, TRUE));
    $this->assertSame([$manager->getEmail()], $this->interceptedMails[1]['reply_to']);
  }

  /**
   * Submit without a manager only sends the organization mail.
   */
  public function testSubmitWithoutManager(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $project->getOwner()->set('field_manager', NULL)->save();
    $this->assertTrue($this->mailer()->submit($project));
    $entries = $this->loadEntriesBySubtype($project);
    $this->assertSame(['mailer.project_transition.submit_organization'], array_keys($entries));
    $this->assertStringContainsString('Dein youvo-Team', $entries['mailer.project_transition.submit_organization'][0]->get('body')->value);
  }

  /**
   * The mediate event mails the organization, each creative and the staff.
   */
  public function testMediate(): void {

    $project = $this->createMailableProject();
    $organization = $project->getOwner();
    $creatives = [$this->createCreative('a'), $this->createCreative('b')];
    $event = new ProjectMediateEvent($project);
    $event->setCreatives($creatives);
    $this->container->get('event_dispatcher')->dispatch($event);

    $this->assertCount(4, $this->loadEntries($project));
    $entries = $this->loadEntriesBySubtype($project);
    $names = $creatives[0]->getName() . ', ' . $creatives[1]->getName();

    $to_organization = $entries['mailer.project_transition.mediate_organization'][0];
    $this->assertSame([$organization->getEmail()], json_decode($to_organization->get('recipients')->value, TRUE));
    $this->assertStringContainsString($names, $to_organization->get('body')->value);

    $this->assertCount(2, $entries['mailer.project_transition.mediate_creative']);
    foreach ($entries['mailer.project_transition.mediate_creative'] as $delta => $to_creative) {
      $this->assertSame([$creatives[$delta]->getEmail()], json_decode($to_creative->get('recipients')->value, TRUE));
      $this->assertStringContainsString($creatives[$delta]->getName(), $to_creative->get('body')->value);
      $this->assertStringContainsString($organization->getEmail(), $to_creative->get('body')->value);
    }

    $to_staff = $entries['mailer.project_transition.mediate_staff'][0];
    $this->assertSame([$this->siteMail()], json_decode($to_staff->get('recipients')->value, TRUE));
    $this->assertStringContainsString($names, $to_staff->get('body')->value);
    $this->assertStringContainsString($project->getTitle(), $to_staff->get('subject')->value);
  }

  /**
   * One failing recipient does not stop the others.
   *
   * A creative without a mail address makes deliver() return FALSE without
   * rendering, so nothing is recorded for it, while the other mails go out.
   */
  public function testMediateContinuesAfterFailure(): void {
    $project = $this->createMailableProject();
    $creatives = [$this->createCreative('a', ''), $this->createCreative('b')];
    $this->assertFalse($this->mailer()->mediate($project, $creatives));
    $entries = $this->loadEntriesBySubtype($project);
    // Organization, the second creative and the staff; none for the first.
    $this->assertCount(3, $this->loadEntries($project));
    $this->assertCount(1, $entries['mailer.project_transition.mediate_creative']);
    $this->assertSame([$creatives[1]->getEmail()], json_decode($entries['mailer.project_transition.mediate_creative'][0]->get('recipients')->value, TRUE));
    $this->assertCount(1, $entries['mailer.project_transition.mediate_staff']);
  }

  /**
   * Complete mails carry feedback doors per recipient and record no link.
   */
  public function testCompleteCarriesFeedbackDoors(): void {

    $project = $this->createMailableProject(ProjectState::Ongoing);
    $a = $this->createCreative('a');
    $b = $this->createCreative('b');
    $project->appendParticipant($a)->appendParticipant($b)->save();
    $this->assertTrue($this->mailer()->complete($project));

    $this->assertCount(3, $this->interceptedMails);
    $owner = $project->getOwner();
    $recipients = [$owner->getEmail() => $owner, $a->getEmail() => $a, $b->getEmail() => $b];
    $website_url = rtrim((string) $this->container->get('config.factory')->get('youvo.settings')->get('website_url'), '/');
    foreach ($this->interceptedMails as $mail) {
      $recipient = $recipients[$mail['to'][0]];
      $this->assertDoor($mail['html'], $recipient->id(), NULL, DoorTarget::Feedback, '', 'Door of the recipient: ' . $mail['to'][0]);
      $this->assertStringNotContainsString('/feedback/creative/', $mail['html']);
      $this->assertStringNotContainsString('/feedback/organization/', $mail['html']);
    }
    // The results link stays plain.
    $this->assertStringContainsString($website_url . '/projects/' . $project->uuid(), $this->interceptedMails[1]['html']);

    $this->assertCount(3, $this->loadEntries($project));
    $entries = $this->loadEntriesBySubtype($project);
    $organization_body = $entries['mailer.project_transition.complete_organization'][0]->get('body')->value;
    $this->assertStringContainsString($a->getName() . ', ' . $b->getName(), $organization_body);
    $this->assertStringContainsString('[Link entfernt]', $organization_body);
    $this->assertStringNotContainsString('/go/', $organization_body);
    $this->assertCount(2, $entries['mailer.project_transition.complete_creative']);
    foreach ($entries['mailer.project_transition.complete_creative'] as $entry) {
      $body = $entry->get('body')->value;
      $this->assertStringContainsString('[Link entfernt]', $body);
      $this->assertStringNotContainsString('/go/', $body);
      $this->assertStringContainsString('/projects/' . $project->uuid(), $body);
    }
  }

}
