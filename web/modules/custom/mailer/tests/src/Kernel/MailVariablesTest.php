<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\mailer\MailVariables;

/**
 * Tests the neutral fallbacks of the mail variables.
 *
 * @group mailer
 */
class MailVariablesTest extends MailerKernelTestBase {

  /**
   * Gets the mail variables service.
   */
  protected function mailVariables(): MailVariables {
    return $this->container->get('mailer.variables');
  }

  /**
   * A manager without a name falls back to the team, as a missing one does.
   */
  public function testManagerWithEmptyNameFallsBackToTeam(): void {
    $project = $this->createMailableProject();
    $project->getOwner()->getManager()->set('field_name', '')->save();
    $variables = $this->mailVariables()->project($project);
    $this->assertSame('Dein youvo-Team', $variables['manager']);
    $this->assertSame('manager@example.com', $variables['manager_email']);
  }

  /**
   * A missing manager falls back to the team.
   */
  public function testMissingManagerFallsBackToTeam(): void {
    $project = $this->createMailableProject();
    $project->getOwner()->set('field_manager', NULL)->save();
    $this->assertSame('Dein youvo-Team', $this->mailVariables()->project($project)['manager']);
  }

  /**
   * An existing manager name is kept.
   */
  public function testManagerNameIsKept(): void {
    $project = $this->createMailableProject();
    $this->assertSame('Manu Manager', $this->mailVariables()->project($project)['manager']);
  }

  /**
   * An empty organization contact falls back to a neutral greeting.
   */
  public function testEmptyContactFallsBack(): void {
    $project = $this->createMailableProject();
    $organization = $project->getOwner();
    $organization->set('field_contact', '')->set('field_name', 'Org e.V.')->save();
    $variables = $this->mailVariables()->project($project);
    $this->assertSame('zusammen', $variables['contact']);
    // The contact line shows the organization name instead of a greeting.
    $this->assertSame('Org e.V.', $variables['organization_contact']);
  }

  /**
   * The contact is kept when it is set.
   */
  public function testContactIsKept(): void {
    $project = $this->createMailableProject();
    $variables = $this->mailVariables()->project($project);
    $this->assertSame('Olga Org', $variables['contact']);
    $this->assertSame('Olga Org', $variables['organization_contact']);
  }

  /**
   * An empty creative name falls back to a neutral word.
   */
  public function testEmptyCreativeNameFallsBack(): void {
    $creative = $this->createCreative();
    $creative->set('field_name', '')->save();
    $this->assertSame('Kreative', $this->mailVariables()->creative($creative)['creative_name']);
    $named = $this->createMailableCreative('Kim');
    $this->assertSame('Kim', $this->mailVariables()->creative($named)['creative_name']);
  }

  /**
   * The manager email falls back to the site mail without a manager.
   */
  public function testManagerEmailFallsBackToSiteMail(): void {
    $project = $this->createMailableProject();
    $this->assertSame('manager@example.com', $this->mailVariables()->project($project)['manager_email']);
    $project->getOwner()->set('field_manager', NULL)->save();
    $this->assertSame('noreply@youvo.org', $this->mailVariables()->project($project)['manager_email']);
  }

}
