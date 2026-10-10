<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use Drupal\projects\Event\ProjectPublishEvent;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\symfony_mailer\EmailInterface;

/**
 * Tests the project transition mailer.
 *
 * @group mailer
 */
class ProjectTransitionMailerTest extends MailerKernelTestBase {

  /**
   * Creates a saved project whose owner has a mail address.
   */
  protected function createMailableProject(): ProjectInterface {
    $project = $this->createProject(ProjectState::Open);
    $organization = $project->getOwner();
    $organization->set('mail', 'organization@example.com')->save();
    $organization->getManager()->set('mail', 'manager@example.com')->save();
    $project->save();
    return $project;
  }

  /**
   * Loads the recorded correspondence of a project.
   */
  protected function loadEntries(int|string $project_id): array {
    return $this->container->get('entity_type.manager')
      ->getStorage('correspondence')
      ->loadByProperties(['project' => $project_id]);
  }

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
   * The mail carries a valid From address and the manager as reply-to.
   */
  public function testHeaders(): void {
    $project = $this->createMailableProject();
    $mailer = $this->container->get(ProjectTransitionMailerInterface::class);
    $headers = [];
    $mailer->addCallback(static function (EmailInterface $email) use (&$headers): void {
      $headers['from'] = array_map(static fn ($a) => $a->getEmail(), $email->getFrom());
      $headers['reply_to'] = array_map(static fn ($a) => $a->getEmail(), $email->getReplyTo());
    }, EmailInterface::PHASE_POST_RENDER, 951, 'header_capture');
    $this->assertTrue($mailer->publish($project));
    $this->assertSame(['noreply@youvo.org'], $headers['from']);
    $this->assertSame(['manager@example.com'], $headers['reply_to']);
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
