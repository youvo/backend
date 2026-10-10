<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\projects\Event\ProjectMediateEvent;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\Event\ProjectSubmitEvent;
use Drupal\projects\ProjectState;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Tests the project transition mailer.
 *
 * @group mailer
 */
class ProjectTransitionMailerTest extends MailerKernelTestBase {

  /**
   * The publish mail is rendered from the policy and recorded.
   */
  public function testPublish(): void {
    $project = $this->createMailableProject();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    $this->assertTrue($mailer->publish($project));

    $this->assertCount(1, $this->capturedMails);
    $this->assertSame(['organization@example.com'], $this->capturedMails[0]['to']);
    $this->assertStringContainsString($project->getTitle(), $this->capturedMails[0]['subject']);
    $this->assertStringContainsString('<p>', $this->capturedMails[0]['html']);

    $entries = $this->loadEntries($project->id());
    $this->assertCount(1, $entries);
    $this->assertSame('mailer.project_transition.publish_organization', reset($entries)->getSubtype());
    $this->assertTrue(reset($entries)->isSent());
  }

  /**
   * Captures the From and Reply-To addresses of the mails of a mailer.
   *
   * @return array<int, array{from: string[], reply_to: string[]}>
   *   The addresses, one entry per sent mail.
   */
  protected function captureHeaders(ProjectTransitionMailerInterface $mailer, callable $send): array {
    $headers = [];
    $mailer->addCallback(static function (EmailInterface $email) use (&$headers): void {
      $headers[] = [
        'from' => array_map(static fn ($a) => $a->getEmail(), $email->getFrom()),
        'reply_to' => array_map(static fn ($a) => $a->getEmail(), $email->getReplyTo()),
      ];
    }, EmailInterface::PHASE_POST_RENDER, 951, 'header_capture');
    $send($mailer);
    return $headers;
  }

  /**
   * The mail carries a valid From address and the manager as reply-to.
   */
  public function testHeaders(): void {
    $project = $this->createMailableProject();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $headers = $this->captureHeaders($mailer, static fn ($m) => $m->publish($project));
    $this->assertSame(['noreply@youvo.org'], $headers[0]['from']);
    $this->assertSame(['manager@example.com'], $headers[0]['reply_to']);
  }

  /**
   * Without a manager the reply-to address is the site mail address.
   */
  public function testReplyToFallsBackToSiteMail(): void {
    $project = $this->createMailableProject();
    $project->getOwner()->set('field_manager', NULL)->save();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $headers = $this->captureHeaders($mailer, static fn ($m) => $m->publish($project));
    $this->assertSame(['noreply@youvo.org'], $headers[0]['from']);
    $this->assertSame(['noreply@youvo.org'], $headers[0]['reply_to']);
  }

  /**
   * The mail to the manager itself has the site mail address as reply-to.
   */
  public function testSubmitManagerMailReplyToSiteMail(): void {
    $this->config('system.site')->set('mail', 'team@example.com')->save();
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $headers = $this->captureHeaders($mailer, static fn ($m) => $m->submit($project));
    // Only the first mail of a mailer is captured: the manager mail.
    $this->assertSame(['team@example.com'], $headers[0]['reply_to']);
  }

  /**
   * Without a manager the sender name falls back to the team.
   */
  public function testPublishWithoutManager(): void {
    $project = $this->createMailableProject();
    $project->getOwner()->set('field_manager', NULL)->save();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    $this->assertTrue($mailer->publish($project));
    $this->assertStringContainsString('Dein youvo-Team', $this->capturedMails[0]['html']);
  }

  /**
   * A failing transport returns FALSE, does not throw and is recorded.
   */
  public function testFailedSendIsRecorded(): void {
    $project = $this->createMailableProject();
    $this->useFailingTransport();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->assertFalse($mailer->publish($project));
    $entries = $this->loadEntries($project->id());
    $this->assertCount(1, $entries);
    $this->assertFalse(reset($entries)->isSent());
  }

  /**
   * A policy without content is skipped and not recorded.
   */
  public function testMissingPolicyIsNotRecorded(): void {
    $project = $this->createMailableProject();
    $this->container->get('entity_type.manager')
      ->getStorage('mailer_policy')
      ->load('mailer.project_transition.publish_organization')
      ->delete();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    $this->assertTrue($mailer->publish($project));
    $this->assertCount(0, $this->capturedMails);
    $this->assertCount(0, $this->loadEntries($project->id()));
  }

  /**
   * The publish event triggers the publish mail.
   */
  public function testSubscriberSendsOnPublish(): void {
    $project = $this->createMailableProject();
    $this->eventDispatcher->dispatch(new ProjectPublishEvent($project));
    $entries = $this->loadEntries($project->id());
    $this->assertCount(1, $entries);
  }

  /**
   * Submit sends a mail to the manager and one to the organization.
   */
  public function testSubmitSendsTwoMails(): void {
    $project = $this->createMailableProject();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->assertTrue($mailer->submit($project));
    $entries = $this->loadEntriesBySubtype($project->id());
    $this->assertCount(2, $this->loadEntries($project->id()));
    $manager = $entries['mailer.project_transition.submit_manager'][0];
    $this->assertSame('["manager@example.com"]', $manager->get('recipients')->value);
    $this->assertStringContainsString($project->getTitle(), $manager->get('subject')->value);
    $organization = $entries['mailer.project_transition.submit_organization'][0];
    $this->assertSame('["organization@example.com"]', $organization->get('recipients')->value);
  }

  /**
   * Submit without a manager only sends the organization mail.
   */
  public function testSubmitWithoutManager(): void {
    $project = $this->createMailableProject();
    $project->getOwner()->set('field_manager', NULL)->save();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->assertTrue($mailer->submit($project));
    $entries = $this->loadEntriesBySubtype($project->id());
    $this->assertSame(['mailer.project_transition.submit_organization'], array_keys($entries));
    $this->assertStringContainsString('Dein youvo-Team', $entries['mailer.project_transition.submit_organization'][0]->get('body')->value);
  }

  /**
   * The submit event triggers the submit mails.
   */
  public function testSubscriberSendsOnSubmit(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->eventDispatcher->dispatch(new ProjectSubmitEvent($project));
    $this->assertCount(2, $this->loadEntries($project->id()));
  }

  /**
   * Mediate mails the organization, each creative and the staff.
   */
  public function testMediateFansOut(): void {
    $project = $this->createMailableProject();
    $creatives = [$this->createMailableCreative('a'), $this->createMailableCreative('b')];
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->assertTrue($mailer->mediate($project, $creatives));
    $entries = $this->loadEntriesBySubtype($project->id());
    $this->assertCount(4, $this->loadEntries($project->id()));
    $this->assertCount(1, $entries['mailer.project_transition.mediate_organization']);
    $this->assertCount(2, $entries['mailer.project_transition.mediate_creative']);
    $this->assertCount(1, $entries['mailer.project_transition.mediate_staff']);
    $organization_body = $entries['mailer.project_transition.mediate_organization'][0]->get('body')->value;
    $this->assertStringContainsString('a, b', $organization_body);
  }

  /**
   * The staff mail goes to the site mail address and names the creatives.
   */
  public function testMediateNotifiesStaff(): void {
    $this->config('system.site')->set('mail', 'team@example.com')->save();
    $project = $this->createMailableProject();
    $creatives = [$this->createMailableCreative('a')];
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->assertTrue($mailer->mediate($project, $creatives));
    $staff = $this->loadEntriesBySubtype($project->id())['mailer.project_transition.mediate_staff'][0];
    $this->assertSame('["team@example.com"]', $staff->get('recipients')->value);
    $this->assertStringContainsString($creatives[0]->getName(), $staff->get('body')->value);
    $this->assertStringContainsString($project->getTitle(), $staff->get('subject')->value);
  }

  /**
   * The creative mail carries the organization contact data.
   */
  public function testMediateCreativeMail(): void {
    $project = $this->createMailableProject();
    $creative = $this->createMailableCreative('Kim');
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    // Only the first mail of a mailer is captured: the organization mail.
    $this->assertTrue($mailer->mediate($project, [$creative]));
    $this->assertSame(['organization@example.com'], $this->capturedMails[0]['to']);
    $creative_entry = $this->loadEntriesBySubtype($project->id())['mailer.project_transition.mediate_creative'][0];
    $this->assertSame('["Kim@example.com"]', $creative_entry->get('recipients')->value);
    $this->assertStringContainsString('Kim', $creative_entry->get('body')->value);
    $this->assertStringContainsString('organization@example.com', $creative_entry->get('body')->value);
  }

  /**
   * One failing recipient does not stop the others.
   *
   * A creative without a mail address makes deliver() return FALSE without
   * rendering, so nothing is recorded for it, while the other mails go out.
   */
  public function testMediateContinuesAfterFailure(): void {
    $project = $this->createMailableProject();
    $creatives = [$this->createCreative(), $this->createMailableCreative('b')];
    $this->assertEmpty($creatives[0]->getEmail());
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->assertFalse($mailer->mediate($project, $creatives));
    $entries = $this->loadEntriesBySubtype($project->id());
    // Organization, the second creative and the staff; none for the first.
    $this->assertCount(3, $this->loadEntries($project->id()));
    $this->assertCount(1, $entries['mailer.project_transition.mediate_creative']);
    $this->assertSame('["b@example.com"]', $entries['mailer.project_transition.mediate_creative'][0]->get('recipients')->value);
    $this->assertCount(1, $entries['mailer.project_transition.mediate_staff']);
  }

  /**
   * A failing transport is recorded for every mail of a fan-out.
   */
  public function testMediateWithFailingTransport(): void {
    $project = $this->createMailableProject();
    $creatives = [$this->createMailableCreative('a'), $this->createMailableCreative('b')];
    $this->useFailingTransport();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->assertFalse($mailer->mediate($project, $creatives));
    $entries = $this->loadEntries($project->id());
    $this->assertCount(4, $entries);
    foreach ($entries as $entry) {
      $this->assertFalse($entry->isSent());
    }
  }

  /**
   * The mediate event triggers the mediate mails.
   */
  public function testSubscriberSendsOnMediate(): void {
    $project = $this->createMailableProject();
    $event = new ProjectMediateEvent($project);
    $event->setCreatives([$this->createMailableCreative('a')]);
    $this->eventDispatcher->dispatch($event);
    $this->assertCount(3, $this->loadEntries($project->id()));
  }

  /**
   * Complete sends one mail to the organization and one per participant.
   */
  public function testCompleteFansOut(): void {
    $project = $this->createMailableProject();
    $a = $this->createMailableCreative('a');
    $b = $this->createMailableCreative('b');
    $project->appendParticipant($a)->appendParticipant($b)->save();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->assertTrue($mailer->complete($project));
    $entries = $this->loadEntriesBySubtype($project->id());
    $this->assertCount(3, $this->loadEntries($project->id()));
    $this->assertCount(2, $entries['mailer.project_transition.complete_creative']);

    $organization_body = $entries['mailer.project_transition.complete_organization'][0]->get('body')->value;
    $this->assertStringContainsString('a, b', $organization_body);
    $this->assertStringContainsString('feedback/organization/' . $project->uuid(), $organization_body);
    foreach ($entries['mailer.project_transition.complete_creative'] as $entry) {
      $body = $entry->get('body')->value;
      $this->assertStringContainsString('feedback/creative/' . $project->uuid(), $body);
      $this->assertStringContainsString('results/' . $project->uuid(), $body);
    }
  }

  /**
   * Entity data is escaped in the body, plain in the subject, never evaluated.
   */
  public function testEntityDataIsEscapedAndNotEvaluated(): void {
    $project = $this->createMailableProject();
    $project->set('title', 'A & <b>x</b> {{ 1+1 }}')->save();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    $this->assertTrue($mailer->publish($project));
    $html = $this->capturedMails[0]['html'];
    $this->assertStringContainsString('A &amp; &lt;b&gt;x&lt;/b&gt;', $html);
    $this->assertStringNotContainsString('<b>x</b>', $html);
    $this->assertStringContainsString('{{ 1+1 }}', $html);
    $subject = $this->capturedMails[0]['subject'];
    $this->assertStringContainsString('A & <b>x</b> {{ 1+1 }}', $subject);
    $this->assertStringNotContainsString('&amp;', $subject);
  }

  /**
   * An organization without a mail address does not throw and sends nothing.
   */
  public function testMissingRecipientAddress(): void {
    $project = $this->createProject(ProjectState::Open);
    $project->save();
    $this->assertEmpty($project->getOwner()->getEmail());
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $this->captureMails($mailer);
    $this->assertFalse($mailer->publish($project));
    $this->assertCount(0, $this->capturedMails);
    $this->assertCount(0, $this->loadEntries($project->id()));
  }

}
