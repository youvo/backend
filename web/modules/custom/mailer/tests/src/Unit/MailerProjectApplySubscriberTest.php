<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\mailer\EventSubscriber\MailerProjectApplySubscriber;
use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\EventSubscriber\Action\ProjectApplySubscriber;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the order of the apply mail subscriber.
 */
#[CoversClass(MailerProjectApplySubscriber::class)]
#[Group('mailer')]
class MailerProjectApplySubscriberTest extends UnitTestCase {

  /**
   * The mails are sent after the application is handled.
   *
   * Otherwise the correspondence could record an application that failed.
   */
  public function testRunsAfterApplicationIsHandled(): void {
    $mail = MailerProjectApplySubscriber::getSubscribedEvents()[ProjectApplyEvent::class][1];
    $apply = ProjectApplySubscriber::getSubscribedEvents()[ProjectApplyEvent::class][1];
    $this->assertLessThan($apply, $mail);
  }

}
