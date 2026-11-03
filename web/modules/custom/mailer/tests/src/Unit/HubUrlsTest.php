<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\mailer\HubUrls;
use Drupal\projects\ProjectInterface;
use Drupal\projects\Service\ProjectLifecycleInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the hub URL helper.
 */
#[CoversClass(HubUrls::class)]
#[Group('mailer')]
class HubUrlsTest extends UnitTestCase {

  /**
   * Drafts link to the draft page, other states to the project page.
   *
   * A trailing slash of the configured hub URL is ignored.
   */
  public function testProjectLink(): void {
    $hub_urls = new HubUrls($this->getConfigFactoryStub(['youvo.settings' => ['hub_url' => 'https://hub.example.org/']]));
    $this->assertSame('https://hub.example.org/draft/abc', $hub_urls->project($this->project(TRUE)));
    $this->assertSame('https://hub.example.org/projects/abc', $hub_urls->project($this->project(FALSE)));
  }

  /**
   * Creates a project mock with the UUID abc.
   */
  protected function project(bool $draft): ProjectInterface {
    $lifecycle = $this->createMock(ProjectLifecycleInterface::class);
    $lifecycle->method('isDraft')->willReturn($draft);
    $project = $this->createMock(ProjectInterface::class);
    $project->method('lifecycle')->willReturn($lifecycle);
    $project->method('uuid')->willReturn('abc');
    return $project;
  }

}
