<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectState;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the suggestions of the door link form.
 */
#[Group('mailer')]
class DoorLinkAutocompleteTest extends MailerExistingSiteTestBase {

  /**
   * The administrator who makes the links.
   */
  protected UserInterface $admin;

  /**
   * Creates the administrator.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->admin = $this->createUser([], NULL, TRUE);
  }

  /**
   * Gets the suggestions of a field as the administrator.
   *
   * @param string $kind
   *   The field.
   * @param array<string, string|int> $query
   *   The query parameters.
   *
   * @return array<int, array{value: string, entity_id: string, label: string, info_label: string}>
   *   The tags.
   */
  protected function suggest(string $kind, array $query): array {
    $this->drupalGet('/admin/door-links/autocomplete/' . $kind, ['query' => $query]);
    $this->assertSession()->statusCodeEquals(200);
    $tags = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->assertIsArray($tags);
    return $tags;
  }

  /**
   * Gets the chips of the tags, keyed by entity ID.
   *
   * @return array<string, string>
   *   The chips.
   */
  protected function chips(array $tags): array {
    return array_column($tags, 'info_label', 'entity_id');
  }

  /**
   * Tests that only administrators get suggestions.
   */
  public function testAccess(): void {

    $manager = $this->createCreative('manager');
    $manager->addRole('manager');
    $manager->save();
    $this->drupalLogin($manager);
    $this->drupalGet('/admin/door-links/autocomplete/project');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->admin);
    $this->drupalGet('/admin/door-links/autocomplete/project');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests the people of a project, in order and with their chips.
   */
  public function testRecipientOfProject(): void {

    $project = $this->createMailableProject(ProjectState::Ongoing, TRUE);
    $owner = $project->getOwner();
    $applicant = $this->createCreative();
    $participant = $this->createCreative();
    // The applicant also participates, but is named once.
    $project->appendApplicant($applicant)
      ->appendParticipant($participant)
      ->appendParticipant($applicant)
      ->save();

    $this->drupalLogin($this->admin);
    $tags = $this->suggest('recipient', ['q' => '', 'project' => $project->id()]);

    $this->assertSame([(string) $owner->id(), (string) $applicant->id(), (string) $participant->id()], array_column($tags, 'entity_id'));
    $this->assertSame([
      (string) $owner->id() => 'Prospect',
      (string) $applicant->id() => 'Creative',
      (string) $participant->id() => 'Creative',
    ], $this->chips($tags));
    $this->assertSame($owner->label(), $tags[0]['label']);

    // Without a project, nothing is suggested before typing.
    $this->assertSame([], $this->suggest('recipient', ['q' => '']));
  }

  /**
   * Tests that typed text finds people by name with their chips.
   */
  public function testRecipientByName(): void {

    $name = 'Door ' . $this->randomMachineName();
    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'field_name' => $name . ' Organisation',
      'mail' => strtolower($this->randomMachineName()) . '@example.org',
      'status' => 1,
    ]);
    $organization->addRole(Organization::ROLE_ORGANIZATION);
    $organization->save();
    $this->markEntityForCleanup($organization);
    $creative = $this->createCreative();
    $creative->set('field_name', $name . ' Creative')->save();

    $this->drupalLogin($this->admin);
    $chips = $this->chips($this->suggest('recipient', ['q' => $name]));

    $this->assertSame('Organisation', $chips[(string) $organization->id()] ?? NULL);
    $this->assertSame('Creative', $chips[(string) $creative->id()] ?? NULL);
  }

  /**
   * Tests the state chips of projects.
   */
  public function testProjectChips(): void {

    $draft = $this->createMailableProject(ProjectState::Draft);
    $open = $this->createMailableProject();

    $this->drupalLogin($this->admin);
    $this->assertSame([(string) $draft->id() => 'Entwurf'], $this->chips($this->suggest('project', ['q' => $draft->label()])));
    $this->assertSame([(string) $open->id() => 'Offen'], $this->chips($this->suggest('project', ['q' => $open->label()])));
  }

  /**
   * Tests that the subject field suggests the applicants of the project.
   */
  public function testSubjectOfProject(): void {

    $project = $this->createMailableProject();
    $first = $this->createCreative();
    $second = $this->createCreative();
    $project->appendApplicant($first)->appendApplicant($second)->save();

    $this->drupalLogin($this->admin);
    $tags = $this->suggest('subject', ['q' => '', 'project' => $project->id()]);

    $this->assertSame([
      (string) $first->id() => 'Creative',
      (string) $second->id() => 'Creative',
    ], $this->chips($tags));
  }

}
