<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\creatives\Event\CreativeRegisterEvent;
use Drupal\mailer\Mailer\YouvoUserMailerInterface;
use Drupal\organizations\Event\OrganizationCreateEvent;
use Drupal\projects\ProjectState;
use Drupal\symfony_mailer\EmailInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the user mailer.
 *
 * @group mailer
 */
class YouvoUserMailerTest extends MailerKernelTestBase {

  /**
   * The one-time registration link used in the tests.
   */
  const LINK = 'https://hub.youvo.org/user/reset/5/1700000000/abc-123?x=1&y=2';

  /**
   * Gets the mailer under test.
   */
  protected function mailer(): YouvoUserMailerInterface {
    return $this->container->get(YouvoUserMailerInterface::class);
  }

  /**
   * Counts all recorded correspondence entries.
   */
  protected function countCorrespondence(): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage('correspondence')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  /**
   * The registration mail contains the one-time link and is not recorded.
   */
  public function testRegisterCreative(): void {
    $creative = $this->createMailableCreative('Kim');
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($mailer->registerCreative($creative, self::LINK));
    $this->assertSame(['Kim@example.com'], $this->capturedMails[0]['to']);
    $html = $this->capturedMails[0]['html'];
    $this->assertStringContainsString('Willkommen Kim,', $html);
    $this->assertStringContainsString('href="' . htmlspecialchars(self::LINK) . '"', $html);
    $this->assertSame(0, $this->countCorrespondence());
  }

  /**
   * A creative without name is greeted neutrally.
   */
  public function testRegisterCreativeWithoutName(): void {
    $creative = $this->createMailableCreative('Kim');
    $creative->set('field_name', '')->set('name', 'kim')->save();
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($mailer->registerCreative($creative, self::LINK));
    $this->assertStringContainsString('Willkommen Kreative,', $this->capturedMails[0]['html']);
  }

  /**
   * A new organization notifies the site mail address and is not recorded.
   */
  public function testOrganizationCreatedNotifiesSiteMail(): void {
    $this->config('system.site')->set('mail', 'team@example.com')->save();
    $project = $this->createMailableProject(ProjectState::Draft);
    $organization = $project->getOwner();
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($mailer->notifyOrganizationCreated($organization));
    $this->assertSame(['team@example.com'], $this->capturedMails[0]['to']);
    $html = $this->capturedMails[0]['html'];
    $this->assertStringContainsString($organization->getName(), $this->capturedMails[0]['subject']);
    $this->assertStringContainsString('Olga Org', $html);
    $this->assertStringContainsString('organization@example.com', $html);
    $this->assertSame(0, $this->countCorrespondence());
  }

  /**
   * The organization data is HTML-escaped.
   */
  public function testOrganizationCreatedEscapesText(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $organization = $project->getOwner();
    $organization->set('field_name', '<b>Bold</b> & Co')->save();
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertTrue($mailer->notifyOrganizationCreated($organization));
    $this->assertStringNotContainsString('<b>', $this->capturedMails[0]['html']);
    $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt; &amp; Co', $this->capturedMails[0]['html']);
  }

  /**
   * Without a site mail address nothing is sent.
   */
  public function testOrganizationCreatedWithoutSiteMail(): void {
    $this->config('system.site')->set('mail', '')->save();
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    $this->captureMails($mailer);
    $this->assertFalse($mailer->notifyOrganizationCreated($project->getOwner()));
    $this->assertSame([], $this->capturedMails);
    $this->assertSame(0, $this->countCorrespondence());
  }

  /**
   * The user mails have the site From and the site mail as reply-to.
   */
  public function testHeaders(): void {
    $headers = [];
    $callback = static function (EmailInterface $email) use (&$headers): void {
      $headers[] = [
        'from' => array_map(static fn ($a) => $a->getEmail(), $email->getFrom()),
        'reply_to' => array_map(static fn ($a) => $a->getEmail(), $email->getReplyTo()),
      ];
    };
    $mailer = $this->mailer();
    $mailer->addCallback($callback, EmailInterface::PHASE_POST_RENDER, 951, 'header_capture');
    $this->assertTrue($mailer->registerCreative($this->createMailableCreative('Kim'), self::LINK));
    $project = $this->createMailableProject(ProjectState::Draft);
    $mailer = $this->mailer();
    $mailer->addCallback($callback, EmailInterface::PHASE_POST_RENDER, 951, 'header_capture');
    $this->assertTrue($mailer->notifyOrganizationCreated($project->getOwner()));
    $this->assertCount(2, $headers);
    foreach ($headers as $header) {
      $this->assertSame(['noreply@youvo.org'], $header['from']);
      $this->assertSame(['noreply@youvo.org'], $header['reply_to']);
    }
  }

  /**
   * The creative register event sends the registration mail.
   */
  public function testSubscriberSendsOnCreativeRegister(): void {
    $creative = $this->createMailableCreative('Kim');
    $this->captureMails($this->mailer());
    $this->eventDispatcher->dispatch(new CreativeRegisterEvent($creative, self::LINK));
    $this->assertCount(1, $this->capturedMails);
    $this->assertSame(['Kim@example.com'], $this->capturedMails[0]['to']);
    $this->assertStringContainsString(htmlspecialchars(self::LINK), $this->capturedMails[0]['html']);
    $this->assertSame(0, $this->countCorrespondence());
  }

  /**
   * The organization create event notifies the site mail address.
   */
  public function testSubscriberSendsOnOrganizationCreate(): void {
    $this->config('system.site')->set('mail', 'team@example.com')->save();
    $project = $this->createMailableProject(ProjectState::Draft);
    // The projects module creates a project from the request on this event,
    // which needs project fields that this test does not set up.
    $this->eventDispatcher->removeSubscriber($this->container->get('project.organization_create.event_subscriber'));
    $this->captureMails($this->mailer());
    $this->eventDispatcher->dispatch(new OrganizationCreateEvent($project->getOwner(), new Request()));
    $this->assertCount(1, $this->capturedMails);
    $this->assertSame(['team@example.com'], $this->capturedMails[0]['to']);
  }

}
