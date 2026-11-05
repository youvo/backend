<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\mailer\Mailer\ProjectActionMailerInterface;
use Drupal\projects\Event\ProjectApplyEvent;
use Drupal\projects\Event\ProjectInviteEvent;
use Drupal\projects\Event\ProjectNotifyEvent;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project action mailer.
 */
#[Group('mailer')]
class ProjectActionMailerTest extends MailerExistingSiteTestBase {

  /**
   * Gets the mailer under test.
   */
  protected function mailer(): ProjectActionMailerInterface {
    return $this->container->get(ProjectActionMailerInterface::class);
  }

  /**
   * The apply event mails the organization and the applicant.
   */
  public function testApply(): void {

    $project = $this->createMailableProject();
    $organization = $project->getOwner();
    $manager = $organization->getManager();
    $creative = $this->createCreative();
    $event = new ProjectApplyEvent($project, $creative);
    $event->setMessage('Hallo')->setPhoneNumber('0123 456');
    $this->container->get('event_dispatcher')->dispatch($event);

    $this->assertCount(2, $this->loadEntries($project));
    $entries = $this->loadEntriesBySubtype($project);

    $to_organization = $entries['mailer.project_action.apply_organization'][0];
    $this->assertSame([$organization->getEmail()], json_decode($to_organization->get('recipients')->value, TRUE));
    $body = $to_organization->get('body')->value;
    $this->assertStringContainsString('Hallo', $body);
    $this->assertStringContainsString($creative->getEmail(), $body);
    $this->assertStringContainsString('0123 456', $body);
    $this->assertStringContainsString('[Link entfernt]', $body);
    $this->assertStringContainsString($manager->getName(), $body);
    $this->assertStringContainsString($creative->getName(), $to_organization->get('subject')->value);
    $this->assertStringContainsString($project->getTitle(), $to_organization->get('subject')->value);

    $to_creative = $entries['mailer.project_action.apply_creative'][0];
    $this->assertSame([$creative->getEmail()], json_decode($to_creative->get('recipients')->value, TRUE));
    $body = $to_creative->get('body')->value;
    $this->assertStringContainsString($organization->getEmail(), $body);
    $this->assertStringContainsString($organization->getContact(), $body);
    $this->assertStringContainsString($manager->getEmail(), $body);
    $this->assertStringNotContainsString('0123 456', $body);
  }

  /**
   * Applicant text is HTML-escaped and never evaluated.
   */
  public function testApplyEscapesUserText(): void {
    $project = $this->createMailableProject();
    $this->assertTrue($this->mailer()->apply($project, $this->createCreative(), "<script>alert(1)</script> & {{ 1+1 }}\nZeile", '<1> & 2'));
    $html = $this->interceptedMails[0]['html'];
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; {{ 1+1 }}<br', $html);
    $this->assertStringContainsString('Zeile', $html);
    $this->assertStringContainsString('&lt;1&gt; &amp; 2', $html);
  }

  /**
   * Without a manager the mails name the team and point to the site mail.
   */
  public function testApplyWithoutManager(): void {
    $project = $this->createMailableProject();
    $project->getOwner()->set('field_manager', NULL)->save();
    $this->assertTrue($this->mailer()->apply($project, $this->createCreative(), 'Hallo', ''));
    $entries = $this->loadEntriesBySubtype($project);
    $this->assertStringContainsString('Dein youvo-Team', $entries['mailer.project_action.apply_organization'][0]->get('body')->value);
    $creative_body = $entries['mailer.project_action.apply_creative'][0]->get('body')->value;
    $this->assertStringContainsString('Dein youvo-Team', $creative_body);
    $this->assertStringContainsString($this->siteMail(), $creative_body);
  }

  /**
   * The notify event mails the organization.
   */
  public function testNotify(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('event_dispatcher')->dispatch(new ProjectNotifyEvent($project));
    $this->assertCount(1, $this->interceptedMails);
    $this->assertSame([$project->getOwner()->getEmail()], $this->interceptedMails[0]['to']);
    $this->assertStringContainsString($project->getOwner()->getContact(), $this->interceptedMails[0]['html']);
    $this->assertCount(1, $this->loadEntriesBySubtype($project)['mailer.project_action.notify_organization']);
  }

  /**
   * The notify mail links a draft door that is not recorded.
   */
  public function testNotifyCarriesDraftDoor(): void {

    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertTrue($this->mailer()->notify($project, 'https://example.org/ignored'));

    $entry = $this->loadEntriesBySubtype($project)['mailer.project_action.notify_organization'][0];
    $html = $this->interceptedMails[0]['html'];
    $this->assertStringContainsString('/door/' . $project->getOwner()->id() . '/' . $entry->uuid() . '/draft/', $html);
    $this->assertStringNotContainsString('example.org/ignored', $html);
    $body = $entry->get('body')->value;
    $this->assertStringContainsString('[Link entfernt]', $body);
    $this->assertStringNotContainsString('/door/', $body);
  }

  /**
   * The organization gets a door to the applicant, the applicant none.
   */
  public function testApplyCarriesApplicantDoor(): void {

    $project = $this->createMailableProject();
    $creative = $this->createCreative();
    $this->assertTrue($this->mailer()->apply($project, $creative, 'Hallo', ''));

    $entries = $this->loadEntriesBySubtype($project);
    $to_organization = $entries['mailer.project_action.apply_organization'][0];
    $door = '#/door/' . $project->getOwner()->id() . '/' . $to_organization->uuid() . '/applicant/\\d+/[^/"]+/' . $creative->uuid() . '"#';
    $this->assertSame([$project->getOwner()->getEmail()], $this->interceptedMails[0]['to']);
    $this->assertMatchesRegularExpression($door, $this->interceptedMails[0]['html']);
    $body = $to_organization->get('body')->value;
    $this->assertStringContainsString('[Link entfernt]', $body);
    $this->assertStringNotContainsString('/door/', $body);

    $this->assertSame([$creative->getEmail()], $this->interceptedMails[1]['to']);
    $this->assertStringNotContainsString('/door/', $this->interceptedMails[1]['html']);
  }

  /**
   * The invite event mails the one-time link of the prospect.
   */
  public function testInvite(): void {
    $project = $this->createMailableProject(ProjectState::Draft, TRUE);
    $organization = $project->getOwner();
    $this->container->get('event_dispatcher')->dispatch(new ProjectInviteEvent($project));
    $this->assertCount(1, $this->interceptedMails);
    $this->assertStringContainsString('/invite/' . $organization->id() . '/', $this->interceptedMails[0]['html']);
    $this->assertStringContainsString('30 Tage', $this->interceptedMails[0]['html']);
    $entries = $this->loadEntriesBySubtype($project);
    $this->assertCount(1, $entries['mailer.project_action.invite_organization']);
    $body = $entries['mailer.project_action.invite_organization'][0]->get('body')->value;
    $this->assertStringNotContainsString('/invite/', $body);
    $this->assertStringContainsString('[Link entfernt]', $body);
  }

  /**
   * The link is not recorded in any of its encoded forms.
   *
   * Notify and invite share the code that passes the link as a secret.
   */
  public function testLinkIsNotRecorded(): void {

    $link = 'https://hub.youvo.org/invite/5/1700000000/abc-123?next=/a&b=c#frag';
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertTrue($this->mailer()->invite($project, $link));
    // The sent mail keeps the real link.
    $this->assertStringContainsString(htmlspecialchars($link), $this->interceptedMails[0]['html']);

    $entry = $this->loadEntriesBySubtype($project)['mailer.project_action.invite_organization'][0];
    foreach (['body' => $entry->get('body')->value, 'subject' => $entry->get('subject')->value] as $field => $stored) {
      foreach ([$link, htmlspecialchars($link), htmlspecialchars($link, ENT_QUOTES), rawurlencode($link)] as $form) {
        $this->assertStringNotContainsString($form, $stored, "The $field has the link as $form.");
      }
      $this->assertStringNotContainsString('abc-123', $stored);
    }
    $this->assertStringContainsString('[Link entfernt]', $entry->get('body')->value);
  }

  /**
   * The correspondence is recorded under a UUID chosen before sending.
   */
  public function testDeliverKeepsGivenUuid(): void {

    $project = $this->createMailableProject();
    $uuid = $this->container->get('uuid')->generate();
    $mailer = $this->mailer();
    $create = new \ReflectionMethod($mailer, 'projectEmail');
    $deliver = new \ReflectionMethod($mailer, 'deliver');
    $email = $create->invoke($mailer, 'notify_organization', $project, ['link' => 'https://example.org/x']);
    $this->assertTrue($deliver->invoke($mailer, $email, $project, 'notify_organization', $project->getOwner()->getEmail(), [], 'automatic', NULL, $uuid));

    $entries = $this->loadEntries($project);
    $this->assertCount(1, $entries);
    $this->assertSame($uuid, $entries[0]->uuid());
  }

}
