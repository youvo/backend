<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\creatives\Event\CreativeRegisterEvent;
use Drupal\mailer\Mailer\YouvoUserMailerInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the user mailer.
 */
#[Group('mailer')]
class YouvoUserMailerTest extends MailerExistingSiteTestBase {

  /**
   * The one-time registration link used in the tests.
   */
  const string LINK = 'https://hub.youvo.org/user/reset/5/1700000000/abc-123?x=1&y=2';

  /**
   * Gets the mailer under test.
   */
  protected function mailer(): YouvoUserMailerInterface {
    return $this->container->get(YouvoUserMailerInterface::class);
  }

  /**
   * The register event mails the one-time link, which is not recorded.
   */
  public function testRegisterCreative(): void {
    $creative = $this->createCreative();
    $this->container->get('event_dispatcher')->dispatch(new CreativeRegisterEvent($creative, static::LINK));
    $this->assertCount(1, $this->interceptedMails);
    $this->assertSame([$creative->getEmail()], $this->interceptedMails[0]['to']);
    $html = $this->interceptedMails[0]['html'];
    $this->assertStringContainsString('Willkommen ' . $creative->getName() . ',', $html);
    $this->assertStringContainsString('href="' . htmlspecialchars(static::LINK) . '"', $html);
    $this->assertSame(0, $this->countEntriesTo($creative->getEmail()));
  }

  /**
   * A creative without name is greeted neutrally.
   */
  public function testRegisterCreativeWithoutName(): void {
    $creative = $this->createCreative();
    $creative->set('field_name', '')->save();
    $this->assertTrue($this->mailer()->registerCreative($creative, static::LINK));
    $this->assertStringContainsString('Willkommen Kreative,', $this->interceptedMails[0]['html']);
  }

  /**
   * A new organization notifies the site mail address.
   */
  public function testOrganizationCreated(): void {
    $organization = $this->createMailableProject(ProjectState::Draft)->getOwner();
    $this->assertTrue($this->mailer()->notifyOrganizationCreated($organization));
    $this->assertSame([$this->siteMail()], $this->interceptedMails[0]['to']);
    $this->assertStringContainsString($organization->getName(), $this->interceptedMails[0]['subject']);
    $html = $this->interceptedMails[0]['html'];
    $this->assertStringContainsString($organization->getContact(), $html);
    $this->assertStringContainsString($organization->getEmail(), $html);
  }

  /**
   * The organization data is HTML-escaped.
   */
  public function testOrganizationCreatedEscapesText(): void {
    $organization = $this->createMailableProject(ProjectState::Draft)->getOwner();
    $organization->set('field_name', '<b>Bold</b> & Co')->save();
    $this->assertTrue($this->mailer()->notifyOrganizationCreated($organization));
    $this->assertStringNotContainsString('<b>', $this->interceptedMails[0]['html']);
    $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt; &amp; Co', $this->interceptedMails[0]['html']);
  }

  /**
   * The proposal mail thanks the organization without any text of the proposal.
   */
  public function testConfirmOrganizationProposal(): void {
    $organization = $this->createMailableProject(ProjectState::Draft)->getOwner();
    $organization->set('field_name', 'Win 500 euros: www.scam.example')->save();
    $this->assertTrue($this->mailer()->confirmOrganizationProposal($organization));
    $this->assertCount(1, $this->interceptedMails);
    $this->assertSame([$organization->getEmail()], $this->interceptedMails[0]['to']);
    $this->assertSame('Danke für Euren Projektvorschlag', $this->interceptedMails[0]['subject']);
    $html = $this->interceptedMails[0]['html'];
    $this->assertStringContainsString('vielen Dank für Euren Projektvorschlag', $html);
    $this->assertStringNotContainsString('scam', $html);
    $this->assertStringNotContainsString($organization->getContact(), $html);
    $this->assertSame(0, $this->countEntriesTo($organization->getEmail()));
  }

  /**
   * The user mails have no project, so replies go to the site mail address.
   */
  public function testRepliesGoToSiteMail(): void {
    $organization = $this->createMailableProject(ProjectState::Draft)->getOwner();
    $this->assertTrue($this->mailer()->registerCreative($this->createCreative(), static::LINK));
    $this->assertTrue($this->mailer()->notifyOrganizationCreated($organization));
    $this->assertTrue($this->mailer()->confirmOrganizationProposal($organization));
    $this->assertCount(3, $this->interceptedMails);
    foreach ($this->interceptedMails as $mail) {
      $this->assertSame([$this->siteMail()], $mail['reply_to'], $mail['tag']);
    }
  }

}
