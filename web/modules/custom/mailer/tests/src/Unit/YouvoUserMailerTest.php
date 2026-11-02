<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\creatives\Entity\Creative;
use Drupal\mailer\CorrespondenceRecorder;
use Drupal\mailer\EventSubscriber\MailerOrganizationCreateSubscriber;
use Drupal\mailer\Mailer\YouvoUserMailer;
use Drupal\mailer\Mailer\YouvoUserMailerInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\organizations\Event\OrganizationCreateEvent;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\MailerLookupInterface;
use Drupal\symfony_mailer\MailerPlusInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the user mailer and its organization create subscriber.
 */
#[CoversClass(YouvoUserMailer::class)]
#[CoversClass(MailerOrganizationCreateSubscriber::class)]
#[Group('mailer')]
class YouvoUserMailerTest extends UnitTestCase {

  /**
   * The one-time link is passed to deliver() as a secret.
   */
  public function testRegisterCreativePassesLinkAsSecret(): void {

    $email = $this->createMock(EmailInterface::class);
    $email->method('setVariables')->willReturnSelf();
    $creative = $this->createMock(Creative::class);
    $creative->method('getName')->willReturn('Kim');
    $creative->method('getEmail')->willReturn('kim@example.com');

    $mailer = $this->getMockBuilder(YouvoUserMailer::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['newEmail', 'deliver'])
      ->getMock();
    $mailer->method('newEmail')->willReturn($email);
    $mailer->expects($this->once())
      ->method('deliver')
      ->with($email, NULL, 'register_creative', 'kim@example.com', ['https://hub.youvo.org/reset/abc'])
      ->willReturn(TRUE);

    $this->assertTrue($mailer->registerCreative($creative, 'https://hub.youvo.org/reset/abc'));
  }

  /**
   * Without a site mail address the staff mail is neither sent nor recorded.
   */
  public function testOrganizationCreatedWithoutSiteMail(): void {

    $email = $this->createMock(EmailInterface::class);
    $email->method('setVariables')->willReturnSelf();
    $email->expects($this->never())->method('setTo');
    $recorder = $this->createMock(CorrespondenceRecorder::class);
    $recorder->expects($this->never())->method('recordMail');
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error')->with($this->stringContains('no recipient'));
    $organization = $this->createMock(Organization::class);
    $organization->method('getName')->willReturn('Org');

    $mailer = $this->getMockBuilder(YouvoUserMailer::class)
      ->setConstructorArgs([
        $this->createMock(MailerPlusInterface::class),
        $this->createMock(MailerLookupInterface::class),
        'mailer.user',
        $recorder,
        $logger,
        $this->getConfigFactoryStub(['system.site' => ['mail' => '']]),
      ])
      ->onlyMethods(['newEmail'])
      ->getMock();
    $mailer->method('newEmail')->willReturn($email);

    $this->assertFalse($mailer->notifyOrganizationCreated($organization));
  }

  /**
   * The organization create event notifies the staff and the organization.
   */
  public function testSubscriberSendsBothMails(): void {
    $organization = $this->createMock(Organization::class);
    $mailer = $this->createMock(YouvoUserMailerInterface::class);
    $mailer->expects($this->once())->method('notifyOrganizationCreated')->with($organization);
    $mailer->expects($this->once())->method('confirmOrganizationProposal')->with($organization);
    (new MailerOrganizationCreateSubscriber($mailer))->mail(new OrganizationCreateEvent($organization, new Request()));
  }

}
