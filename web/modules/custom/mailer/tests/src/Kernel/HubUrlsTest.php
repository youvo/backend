<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\projects\ProjectState;

/**
 * Tests the hub URL helper.
 *
 * @group mailer
 */
class HubUrlsTest extends MailerKernelTestBase {

  /**
   * A draft project links to the draft page.
   */
  public function testDraftLink(): void {
    $this->config('mailer.settings')->set('hub_url', 'https://hub.example.org')->save();
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertSame('https://hub.example.org/draft/' . $project->uuid(), $this->container->get('mailer.hub_urls')->project($project));
  }

  /**
   * Other states link to the project page; a trailing slash is ignored.
   */
  public function testProjectLink(): void {
    $this->config('mailer.settings')->set('hub_url', 'https://hub.example.org/')->save();
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertSame('https://hub.example.org/projects/' . $project->uuid(), $this->container->get('mailer.hub_urls')->project($project));
  }

}
