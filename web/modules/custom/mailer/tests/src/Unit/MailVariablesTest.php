<?php

namespace Drupal\Tests\mailer\Unit;

use Drupal\creatives\Entity\Creative;
use Drupal\mailer\MailVariables;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the neutral fallbacks of the mail variables.
 *
 * The fallbacks for the manager are tested with the mailers on the site.
 */
#[CoversClass(MailVariables::class)]
#[Group('mailer')]
class MailVariablesTest extends UnitTestCase {

  /**
   * An empty organization contact falls back to a neutral greeting.
   *
   * The contact line shows the organization name instead of the greeting.
   */
  public function testEmptyContactFallsBack(): void {

    $organization = $this->createMock(Organization::class);
    $organization->method('getContact')->willReturn('');
    $organization->method('getName')->willReturn('Org e.V.');
    $organization->method('getManager')->willReturn(NULL);
    $project = $this->createMock(ProjectInterface::class);
    $project->method('getOwner')->willReturn($organization);
    $project->method('getTitle')->willReturn('Title');
    $mail_variables = new MailVariables($this->getConfigFactoryStub(['system.site' => ['mail' => 'site@example.org']]));
    $mail_variables->setStringTranslation($this->getStringTranslationStub());

    $variables = $mail_variables->project($project);
    $this->assertSame('zusammen', $variables['contact']);
    $this->assertSame('Org e.V.', $variables['organization_contact']);
  }

  /**
   * An empty creative name falls back to a neutral word.
   */
  public function testEmptyCreativeNameFallsBack(): void {
    $creative = $this->createMock(Creative::class);
    $creative->method('getName')->willReturn('');
    $creative->method('uuid')->willReturn('abc');
    $mail_variables = new MailVariables($this->getConfigFactoryStub(['youvo.settings' => ['hub_url' => 'https://hub.example.org/']]));
    $mail_variables->setStringTranslation($this->getStringTranslationStub());
    $variables = $mail_variables->creative($creative);
    $this->assertSame('Kreative', $variables['creative_name']);
    $this->assertSame('https://hub.example.org/users/abc', $variables['creative_link']);
  }

}
