<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\mailer\EventSubscriber\MailerProjectApplySubscriber;
use Drupal\mailer\Mailer\ProjectActionMailerInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\projects\EventSubscriber\Action\ProjectApplySubscriber;
use Drupal\projects\ProjectState;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\user\Entity\Role;

/**
 * Tests the project action mailer.
 *
 * @group mailer
 */
class ProjectActionMailerTest extends MailerKernelTestBase {

  /**
   * Gets the mailer under test.
   */
  protected function mailer(): ProjectActionMailerInterface {
    return $this->container->get(ProjectActionMailerInterface::class);
  }

  /**
   * Applicant text is HTML-escaped and never evaluated.
   */
  public function testApplyEscapesUserText(): void {
    $project = $this->createMailableProject();
    $creative = $this->createMailableCreative('a');
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($mailer->apply($project, $creative, "<script>alert(1)</script> & {{ 1+1 }}\nZeile", '<1> & 2'));
    $html = $this->capturedMails[0]['html'];
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; {{ 1+1 }}<br', $html);
    $this->assertStringContainsString('Zeile', $html);
    $this->assertStringContainsString('&lt;1&gt; &amp; 2', $html);
  }

  /**
   * Apply sends one mail to the organization and one to the creative.
   */
  public function testApplySendsTwoMails(): void {
    $project = $this->createMailableProject();
    $creative = $this->createMailableCreative('Kim');
    $this->assertTrue($this->mailer()->apply($project, $creative, 'Hallo', '0123 456'));
    $entries = $this->loadEntriesBySubtype($project->id());
    $this->assertCount(2, $this->loadEntries($project->id()));

    $organization = $entries['mailer.project_action.apply_organization'][0];
    $this->assertSame('["organization@example.com"]', $organization->get('recipients')->value);
    $body = $organization->get('body')->value;
    $this->assertStringContainsString('Hallo', $body);
    $this->assertStringContainsString('Kim@example.com', $body);
    $this->assertStringContainsString('0123 456', $body);
    $this->assertStringContainsString('https://hub.youvo.org/users/' . $creative->uuid(), $body);
    $this->assertStringContainsString('Manu Manager', $body);
    $this->assertStringContainsString($creative->getName(), $organization->get('subject')->value);
    $this->assertStringContainsString($project->getTitle(), $organization->get('subject')->value);

    $creative_entry = $entries['mailer.project_action.apply_creative'][0];
    $this->assertSame('["Kim@example.com"]', $creative_entry->get('recipients')->value);
    $creative_body = $creative_entry->get('body')->value;
    $this->assertStringContainsString('organization@example.com', $creative_body);
    $this->assertStringContainsString('Olga Org', $creative_body);
    $this->assertStringContainsString('manager@example.com', $creative_body);
    $this->assertStringNotContainsString('0123 456', $creative_body);
  }

  /**
   * Without a manager the mails name the team and point to the site mail.
   */
  public function testApplyWithoutManager(): void {
    $project = $this->createMailableProject();
    $project->getOwner()->set('field_manager', NULL)->save();
    $creative = $this->createMailableCreative('Kim');
    $this->assertTrue($this->mailer()->apply($project, $creative, 'Hallo', ''));
    $entries = $this->loadEntriesBySubtype($project->id());
    $this->assertStringContainsString('Dein youvo-Team', $entries['mailer.project_action.apply_organization'][0]->get('body')->value);
    $creative_body = $entries['mailer.project_action.apply_creative'][0]->get('body')->value;
    $this->assertStringContainsString('Dein youvo-Team', $creative_body);
    $this->assertStringContainsString('noreply@youvo.org', $creative_body);
  }

  /**
   * The apply mails have the site From and the manager or site mail reply-to.
   */
  public function testApplyHeaders(): void {
    $project = $this->createMailableProject();
    $creative = $this->createMailableCreative('Kim');
    $headers = [];
    $mailer = $this->mailer();
    $mailer->addCallback(static function (EmailInterface $email) use (&$headers): void {
      $headers[] = [
        'from' => array_map(static fn ($a) => $a->getEmail(), $email->getFrom()),
        'reply_to' => array_map(static fn ($a) => $a->getEmail(), $email->getReplyTo()),
      ];
    }, EmailInterface::PHASE_POST_RENDER, 951, 'header_capture');
    $this->assertTrue($mailer->apply($project, $creative, 'Hallo', ''));
    $this->assertSame(['noreply@youvo.org'], $headers[0]['from']);
    $this->assertSame(['manager@example.com'], $headers[0]['reply_to']);

    $headers = [];
    $project->getOwner()->set('field_manager', NULL)->save();
    $mailer = $this->mailer();
    $mailer->addCallback(static function (EmailInterface $email) use (&$headers): void {
      $headers[] = array_map(static fn ($a) => $a->getEmail(), $email->getReplyTo());
    }, EmailInterface::PHASE_POST_RENDER, 951, 'header_capture');
    $this->assertTrue($mailer->apply($project, $creative, 'Hallo', ''));
    $this->assertSame(['noreply@youvo.org'], $headers[0]);
  }

  /**
   * Notify includes the link in the body.
   */
  public function testNotifyIncludesLink(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($mailer->notify($project, 'https://hub.youvo.org/draft/abc'));
    $this->assertSame(['organization@example.com'], $this->capturedMails[0]['to']);
    $this->assertStringContainsString('Olga Org', $this->capturedMails[0]['html']);
    $this->assertStringContainsString('https://hub.youvo.org/draft/abc', $this->capturedMails[0]['html']);
  }

  /**
   * The invite mail includes the link it is given.
   */
  public function testInviteIncludesLink(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($mailer->invite($project, 'https://hub.youvo.org/invite/abc'));
    $this->assertStringContainsString('https://hub.youvo.org/invite/abc', $this->capturedMails[0]['html']);
    $this->assertStringContainsString('30 Tage', $this->capturedMails[0]['html']);
  }

  /**
   * A mail without recipient is not sent.
   */
  public function testMissingRecipientAddress(): void {
    $project = $this->createProject(ProjectState::Draft);
    $project->save();
    $this->assertFalse($this->mailer()->notify($project, 'https://example.com'));
    $this->assertCount(0, $this->loadEntries($project->id()));
  }

  /**
   * The apply event triggers both apply mails.
   */
  public function testSubscriberSendsOnApply(): void {
    $project = $this->createMailableProject();
    $event = new ProjectApplyEvent($project, $this->createMailableCreative('Kim'));
    $event->setMessage('Hallo')->setPhoneNumber('0123');
    $this->eventDispatcher->dispatch($event);
    $this->assertCount(2, $this->loadEntries($project->id()));
  }

  /**
   * The mails are sent after the application is handled.
   *
   * Otherwise the correspondence could record an application that failed.
   */
  public function testApplyMailRunsAfterApplicationIsHandled(): void {
    $priorities = [];
    foreach ($this->eventDispatcher->getListeners(ProjectApplyEvent::class) as $listener) {
      if (is_array($listener)) {
        $priorities[get_class($listener[0])] = $this->eventDispatcher->getListenerPriority(ProjectApplyEvent::class, $listener);
      }
    }
    $this->assertArrayHasKey(MailerProjectApplySubscriber::class, $priorities);
    $this->assertArrayHasKey(ProjectApplySubscriber::class, $priorities);
    $this->assertLessThan($priorities[ProjectApplySubscriber::class], $priorities[MailerProjectApplySubscriber::class]);
  }

  /**
   * The notify event mails the link that the organizations module sets.
   */
  public function testSubscriberSendsOnNotify(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->captureMails($this->mailer());
    $this->eventDispatcher->dispatch(new ProjectNotifyEvent($project));
    $entries = $this->loadEntriesBySubtype($project->id());
    $this->assertCount(1, $entries['mailer.project_action.notify_organization']);
    // The sent mail has the link, the recorded one has it removed.
    $this->assertStringContainsString('/draft/' . $project->uuid(), $this->capturedMails[0]['html']);
    $this->assertStringContainsString('[Link entfernt]', $entries['mailer.project_action.notify_organization'][0]->get('body')->value);
  }

  /**
   * The invite event mails the one-time link of the prospect.
   */
  public function testSubscriberSendsOnInvite(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $organization = $project->getOwner();
    Role::create(['id' => Organization::ROLE_PROSPECT, 'label' => 'Prospect'])->save();
    $organization->addRole(Organization::ROLE_PROSPECT)->save();
    $this->captureMails($this->mailer());
    $this->eventDispatcher->dispatch(new ProjectInviteEvent($project));
    $entries = $this->loadEntriesBySubtype($project->id());
    $this->assertCount(1, $entries['mailer.project_action.invite_organization']);
    $this->assertStringContainsString('/invite/' . $organization->id() . '/', $this->capturedMails[0]['html']);
    $body = $entries['mailer.project_action.invite_organization'][0]->get('body')->value;
    $this->assertStringNotContainsString('/invite/', $body);
    $this->assertStringContainsString('[Link entfernt]', $body);
  }

  /**
   * Asserts that the sent mail has the link and the recorded one does not.
   */
  protected function assertLinkIsNotRecorded(callable $send, string $link, string $subtype): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($send($mailer, $project, $link));
    // The sent mail keeps the real link.
    $this->assertStringContainsString(htmlspecialchars($link), $this->capturedMails[0]['html']);

    $entry = $this->loadEntriesBySubtype($project->id())[$subtype][0];
    foreach (['body' => $entry->get('body')->value, 'subject' => $entry->get('subject')->value] as $field => $stored) {
      foreach ([$link, htmlspecialchars($link), htmlspecialchars($link, ENT_QUOTES), rawurlencode($link)] as $form) {
        $this->assertStringNotContainsString($form, $stored, "The $field has the link as $form.");
      }
      $this->assertStringNotContainsString('abc-123', $stored);
    }
    $this->assertStringContainsString('[Link entfernt]', $entry->get('body')->value);
  }

  /**
   * The one-time link of the invite mail is not stored as correspondence.
   */
  public function testInviteLinkIsNotRecorded(): void {
    $this->assertLinkIsNotRecorded(
      static fn ($mailer, $project, $link) => $mailer->invite($project, $link),
      'https://hub.youvo.org/invite/5/1700000000/abc-123?next=/a&b=c#frag',
      'mailer.project_action.invite_organization',
    );
  }

  /**
   * The link of the notify mail is not stored as correspondence.
   */
  public function testNotifyLinkIsNotRecorded(): void {
    $this->assertLinkIsNotRecorded(
      static fn ($mailer, $project, $link) => $mailer->notify($project, $link),
      'https://hub.youvo.org/invite/5/1700000000/abc-123?next=/a&b=c#frag',
      'mailer.project_action.notify_organization',
    );
  }

}
