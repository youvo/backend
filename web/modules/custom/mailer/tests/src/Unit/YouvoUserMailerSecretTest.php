<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\creatives\Entity\Creative;
use Drupal\mailer\Mailer\YouvoUserMailer;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that the registration link is handed over as a secret.
 *
 * @group mailer
 */
class YouvoUserMailerSecretTest extends UnitTestCase {

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

}
