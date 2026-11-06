<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\mailer\Mailer\ProjectTransitionMailerInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the compiled MJML wrapper of the theme wraps the mailer mails.
 */
#[Group('mailer')]
class EmailWrapTest extends MailerExistingSiteTestBase {

  /**
   * The wrapper is rendered around the policy body, with a preview text.
   */
  public function testWrapperAppliedToMail(): void {

    $project = $this->createMailableProject();
    $this->assertTrue($this->container->get(ProjectTransitionMailerInterface::class)->publish($project));
    $this->assertCount(1, $this->interceptedMails);

    $html = $this->interceptedMails[0]['html'];
    $this->assertStringContainsString('<!doctype html>', strtolower($html));
    $this->assertStringContainsString('mj-column', $html);
    $this->assertStringContainsString('alt="youvo', $html);
    $this->assertMatchesRegularExpression('#src="https?://[^"]+/assets/logo_email_v2\.png"#', $html);
    // The logo and the footer link to the website, not to the staff-only
    // backend.
    $website_url = rtrim((string) $this->container->get('config.factory')->get('youvo.settings')->get('website_url'), '/');
    $this->assertSame(2, substr_count($html, 'href="' . $website_url . '"'));
    $this->assertStringContainsString('lang="de"', $html);
    // The policy text appears after the header, inside the wrapper body.
    $header = strpos($html, 'alt="youvo');
    $this->assertNotFalse(strpos($html, $project->getTitle(), $header));
    // The hidden preview text skips the greeting.
    $this->assertSame(1, preg_match('#<div style="display:none[^"]*">([^<]*)</div>#', $html, $matches));
    $this->assertStringContainsString($project->getTitle(), $matches[1]);
    $this->assertStringStartsNotWith('Hallo', $matches[1]);
  }

  /**
   * The recorded body is the policy body without the wrapper.
   */
  public function testRecordedBodyHasNoWrapper(): void {

    $project = $this->createMailableProject();
    $this->assertTrue($this->container->get(ProjectTransitionMailerInterface::class)->publish($project));
    $this->assertStringContainsString('mj-column', $this->interceptedMails[0]['html']);

    $entries = $this->loadEntries($project);
    $this->assertCount(1, $entries);
    $body = strtolower((string) $entries[0]->get('body')->value);
    $this->assertStringContainsString(strtolower($project->getTitle()), $body);
    $this->assertStringNotContainsString('<!doctype', $body);
    $this->assertStringNotContainsString('<html', $body);
    $this->assertStringNotContainsString('<style', $body);
    $this->assertStringNotContainsString('mj-column', $body);
  }

}
