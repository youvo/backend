<?php

namespace Drupal\Tests\mailer\ExistingSite;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\correspondence\Entity\Correspondence;
use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Entity\Log;
use Drupal\mailer\Door\DoorLinks;
use Drupal\mailer\Door\DoorTarget;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Traits\DoorTokenTrait;
use Drupal\Tests\youvo\Traits\MailInterceptTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the door link form against the real door.
 *
 * Keeps the logbook listeners, because the door writes project_follow logs.
 */
#[Group('mailer')]
class DoorLinkFormTest extends ExistingSiteBase {

  use DoorTokenTrait;
  use MailInterceptTrait;

  /**
   * The path of the form.
   */
  const string PATH = '/admin/door-links';

  /**
   * The button text of the door.
   */
  const string BUTTON = 'Weiter zu youvo';

  /**
   * The IDs of the projects created in the test.
   *
   * @var int[]
   */
  protected array $projects = [];

  /**
   * The projects taken over by a creative, each with its organization.
   *
   * Deleting a project needs an organization owner.
   *
   * @var array<int, array{0: \Drupal\projects\ProjectInterface, 1: \Drupal\organizations\Entity\Organization}>
   */
  protected array $takenOver = [];

  /**
   * The administrator who makes the links.
   */
  protected UserInterface $admin;

  /**
   * Intercepts mails and creates the administrator.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->interceptMails();
    $this->admin = $this->createUser([], NULL, TRUE);
  }

  /**
   * Removes the follow keys and logs of the created projects.
   */
  protected function tearDown(): void {

    foreach ($this->takenOver as [$project, $organization]) {
      $project->setOwner($organization)->save();
    }

    // The links come from the form, so their mail records are looked up.
    if ($this->projects) {
      $keys = [];
      $ids = $this->container->get('entity_type.manager')->getStorage('correspondence')->getQuery()
        ->accessCheck(FALSE)
        ->condition('project', $this->projects, 'IN')
        ->execute();
      foreach (Correspondence::loadMultiple($ids) as $entry) {
        foreach (DoorTarget::cases() as $target) {
          $keys[] = $entry->uuid() . ':' . $target->value;
        }
      }
      $this->container->get('keyvalue.expirable')->get('mailer.door_follow')->deleteMultiple($keys);

      // The logbook listeners stay, so every log of the projects is removed.
      $ids = $this->container->get('entity_type.manager')->getStorage('log')->getQuery()
        ->accessCheck(FALSE)
        ->condition('project', $this->projects, 'IN')
        ->execute();
      array_walk($ids, fn ($id) => $this->markEntityForCleanup(Log::load($id)));
    }

    parent::tearDown();
  }

  /**
   * Creates a project of an organization with a manager.
   */
  protected function createProject(ProjectState $state = ProjectState::Open, ?UserInterface $owner = NULL): ProjectInterface {

    if ($owner === NULL) {
      $manager = $this->createCreative();
      $manager->addRole('manager');
      $manager->save();
      $owner = $this->createOrganization();
      $owner->set('field_manager', $manager)->save();
    }

    $project = Project::create([
      'uid' => $owner->id(),
      'title' => 'Project ' . $this->randomMachineName(),
      'field_lifecycle' => $state->value,
    ]);
    $project->save();
    $this->markEntityForCleanup($project);
    $this->markEntityForCleanup($project->getResult());
    $this->projects[] = (int) $project->id();

    return $project;
  }

  /**
   * Creates an organization.
   */
  protected function createOrganization(): Organization {
    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.org',
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);
    return $organization;
  }

  /**
   * Creates a creative.
   */
  protected function createCreative(): Creative {
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.com',
      'status' => 1,
    ]);
    $creative->addRole('creative');
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Stops the browser from following redirects into the hub.
   */
  protected function stayOnSite(): void {
    $driver = $this->getSession()->getDriver();
    $this->assertInstanceOf(BrowserKitDriver::class, $driver);
    $client = $driver->getClient();
    $client->followRedirects(FALSE);
    $client->followMetaRefresh(FALSE);
  }

  /**
   * Gets the Tagify input of an entity, as the widget posts it.
   */
  protected function autocomplete(ProjectInterface|UserInterface $entity): string {
    return (string) json_encode([[
      'value' => (string) $entity->id(),
      'entity_id' => (string) $entity->id(),
      'label' => $entity->label(),
    ],
    ]);
  }

  /**
   * Makes a link with the form as the administrator.
   *
   * @return string
   *   The URL shown in the link field.
   */
  protected function generate(ProjectInterface $project, UserInterface $recipient, DoorTarget $target, ?Creative $subject = NULL): string {

    $this->drupalLogin($this->admin);
    $this->drupalGet(static::PATH);
    $this->submitForm([
      'project' => $this->autocomplete($project),
      'recipient' => $this->autocomplete($recipient),
      'target' => $target->value,
      'subject' => $subject ? $this->autocomplete($subject) : '',
    ], 'Link erzeugen');
    $this->assertSession()->statusCodeEquals(200);

    return $this->linkValue();
  }

  /**
   * Gets the URL of the link field.
   */
  protected function linkValue(): string {
    $field = $this->assertSession()->fieldExists('Link');
    $this->assertSame('readonly', $field->getAttribute('readonly'));
    $url = (string) $field->getValue();
    $this->assertStringContainsString('/go/', $url);
    return $url;
  }

  /**
   * Opens a link logged out and presses its button without redirects.
   */
  protected function pressLoggedOut(string $url, string $button = self::BUTTON): void {
    $this->drupalLogout();
    $this->stayOnSite();
    $this->drupalGet((string) parse_url($url, PHP_URL_PATH));
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->pressButton($button);
  }

  /**
   * Gets the mail records of a project.
   *
   * @return \Drupal\correspondence\CorrespondenceInterface[]
   *   The records.
   */
  protected function correspondences(ProjectInterface $project): array {
    $ids = $this->container->get('entity_type.manager')->getStorage('correspondence')->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project->id())
      ->execute();
    return array_values(Correspondence::loadMultiple($ids));
  }

  /**
   * Gets the follow logs of a project.
   *
   * @return \Drupal\logbook\LogInterface[]
   *   The logs.
   */
  protected function follows(ProjectInterface $project): array {
    $ids = $this->container->get('entity_type.manager')->getStorage('log')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'project_follow')
      ->condition('project', $project->id())
      ->execute();
    return array_values(Log::loadMultiple($ids));
  }

  /**
   * Gets the last login of an account from the database.
   */
  protected function lastLogin(UserInterface $account): int {
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache([$account->id()]);
    return (int) $storage->load($account->id())->getLastLoginTime();
  }

  /**
   * Gets the redirect target of the last response.
   */
  protected function location(): ?string {
    return $this->getSession()->getResponseHeader('Location');
  }

  /**
   * Tests that only administrators reach the form.
   */
  public function testAccess(): void {

    $manager = $this->createCreative();
    $manager->addRole('manager');
    $manager->save();
    $this->drupalLogin($manager);
    $this->drupalGet(static::PATH);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->admin);
    $this->drupalGet(static::PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Türlinks erzeugen');
  }

  /**
   * Tests that a made link is recorded without the link and logs in.
   */
  public function testGenerateLink(): void {

    $project = $this->createProject();
    $owner = $project->getOwner();

    $url = $this->generate($project, $owner, DoorTarget::Applicants);

    $this->assertSession()->pageTextContains('Meldet an und schreibt einen Folge-Eintrag');
    $this->assertSession()->pageTextContains('Öffne den Link in einem privaten Fenster.');
    $entries = $this->correspondences($project);
    $this->assertCount(1, $entries);
    $entry = $entries[0];
    $this->assertSame('link', $entry->get('result')->value);
    $this->assertSame('mailer.door.applicants', $entry->getSubtype());
    $this->assertSame('manager', $entry->get('origin')->value);
    $this->assertSame((int) $this->admin->id(), (int) $entry->get('sender')->target_id);
    $this->assertGreaterThan(0, $entry->getOccurredTime());
    $token = $this->doorTokens($url)[0];
    $this->assertDoor($url, $owner->id(), $entry->uuid(), DoorTarget::Applicants);

    // The URL is neither recorded nor kept in the form cache.
    $this->assertStringNotContainsString($token, (string) $entry->get('body')->value);
    $cached = $this->container->get('database')->select('key_value_expire', 'k')
      ->fields('k', ['value'])
      ->condition('collection', ['form', 'form_state'], 'IN')
      ->execute()
      ->fetchCol();
    foreach ($cached as $value) {
      $this->assertStringNotContainsString($token, $value);
    }

    $this->pressLoggedOut($url);

    // Core marks the first redirect after a login.
    $this->assertSame($this->container->get(DoorLinks::class)->loginUrl() . '?check_logged_in=1', $this->location());
    $this->assertGreaterThan(0, $this->lastLogin($owner));
    $this->assertCount(1, $this->follows($project));
  }

  /**
   * Tests the prediction for a staff owner.
   */
  public function testStaffPrediction(): void {

    // A new project needs an organization, so the creative takes it over.
    $owner = $this->createCreative();
    $owner->addRole('manager');
    $owner->save();
    $project = $this->createProject();
    $this->takenOver[] = [$project, $project->getOwner()];
    $project->setOwner($owner)->save();
    $login = $this->lastLogin($owner);

    $url = $this->generate($project, $owner, DoorTarget::Applicants);

    $this->assertSession()->pageTextContains('Meldet nicht an');
    $this->assertSession()->pageTextContains('ist Team');
    $this->pressLoggedOut($url);
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
    $this->assertSame($login, $this->lastLogin($owner));
    $this->assertCount(0, $this->follows($project));
  }

  /**
   * Tests the prediction for an organization of another project.
   */
  public function testNonMemberPrediction(): void {

    $project = $this->createProject();
    $other = $this->createOrganization();

    $url = $this->generate($project, $other, DoorTarget::Applicants);

    $this->assertSession()->pageTextContains('gehört nicht zum Projekt');
    $this->pressLoggedOut($url);
    $this->assertSame(0, $this->lastLogin($other));
    $this->assertCount(0, $this->follows($project));
  }

  /**
   * Tests the prediction for the draft of a prospect.
   */
  public function testProspectPrediction(): void {

    $prospect = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.org',
      'status' => 1,
    ]);
    $prospect->addRole(Organization::ROLE_PROSPECT);
    $prospect->save();
    $this->markEntityForCleanup($prospect);
    $project = $this->createProject(ProjectState::Draft, $prospect);

    $url = $this->generate($project, $prospect, DoorTarget::Draft);

    $this->assertSession()->pageTextContains('Zeigt das Passwortformular');
    $this->assertSession()->pageTextContains('Befördert die Organisation, setzt ihr Passwort, meldet sie an und schreibt einen Folge-Eintrag.');
    $this->drupalLogout();
    $this->drupalGet((string) parse_url($url, PHP_URL_PATH));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('password[pass1]');
  }

  /**
   * Tests the prediction for a blocked organization.
   */
  public function testBlockedPrediction(): void {

    $project = $this->createProject();
    $owner = $project->getOwner();
    $owner->block()->save();

    $url = $this->generate($project, $owner, DoorTarget::Applicants);

    $this->assertSession()->pageTextContains('Die Tür antwortet mit 403: Konto gesperrt.');
    $this->drupalLogout();
    $this->drupalGet((string) parse_url($url, PHP_URL_PATH));
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the applicant target needs a creative and notes non-applicants.
   */
  public function testApplicantNeedsCreative(): void {

    $project = $this->createProject();
    $owner = $project->getOwner();

    $this->drupalLogin($this->admin);
    $this->drupalGet(static::PATH);
    $this->submitForm([
      'project' => $this->autocomplete($project),
      'recipient' => $this->autocomplete($owner),
      'target' => DoorTarget::Applicant->value,
    ], 'Link erzeugen');
    $this->assertSession()->pageTextContains('Wähle die Kreative oder den Kreativen der Bewerbung.');
    $this->assertSession()->fieldNotExists('Link');
    $this->assertCount(0, $this->correspondences($project));

    // An organization is no creative.
    $this->submitForm([
      'project' => $this->autocomplete($project),
      'recipient' => $this->autocomplete($owner),
      'target' => DoorTarget::Applicant->value,
      'subject' => $this->autocomplete($this->createOrganization()),
    ], 'Link erzeugen');
    $this->assertSession()->pageTextContains('Wähle die Kreative oder den Kreativen der Bewerbung.');
    $this->assertSession()->elementExists('css', 'input[name="subject"].error');
    $this->assertSession()->fieldNotExists('Link');
    $this->assertCount(0, $this->correspondences($project));

    $creative = $this->createCreative();
    $url = $this->generate($project, $owner, DoorTarget::Applicant, $creative);

    $this->assertSession()->pageTextContains($creative->getDisplayName() . ' hat sich nicht beworben.');
    $this->assertDoor($url, $owner->id(), NULL, DoorTarget::Applicant, $creative->uuid());
  }

  /**
   * Tests the suggestions of an open project with applicants.
   */
  public function testSuggestionsOpen(): void {

    $project = $this->createProject();
    $first = $this->createCreative();
    $second = $this->createCreative();
    $project->appendApplicant($first)->appendApplicant($second)->save();

    $this->drupalLogin($this->admin);
    $this->drupalGet(static::PATH);
    $this->submitForm(['project' => $this->autocomplete($project)], 'Vorschläge zeigen');

    $rows = $this->getSession()->getPage()->findAll('css', 'table tbody tr');
    $this->assertCount(3, $rows);
    $owner = $project->getOwner()->getDisplayName();
    $this->assertStringContainsString($owner, $rows[0]->getText());
    $this->assertStringContainsString(DoorTarget::Applicants->heading(), $rows[0]->getText());
    foreach ([1 => $first, 2 => $second] as $delta => $applicant) {
      $this->assertStringContainsString($owner, $rows[$delta]->getText());
      $this->assertStringContainsString('Bewerbung: ' . $applicant->getDisplayName(), $rows[$delta]->getText());
    }
    $this->assertSession()->fieldNotExists('Link');

    $this->getSession()->getPage()->pressButton('suggestion_1');

    $url = $this->linkValue();
    $this->assertDoor($url, $project->getOwner()->id(), NULL, DoorTarget::Applicant, $first->uuid());
  }

  /**
   * Tests the suggestions of a completed project.
   */
  public function testSuggestionsCompleted(): void {

    $project = $this->createProject(ProjectState::Completed);
    $creative = $this->createCreative();
    $project->appendParticipant($creative)->save();

    $this->drupalLogin($this->admin);
    $this->drupalGet(static::PATH);
    $this->submitForm(['project' => $this->autocomplete($project)], 'Vorschläge zeigen');

    $rows = $this->getSession()->getPage()->findAll('css', 'table tbody tr');
    $this->assertCount(2, $rows);
    $this->assertStringContainsString($project->getOwner()->getDisplayName(), $rows[0]->getText());
    $this->assertStringContainsString(DoorTarget::Feedback->heading(), $rows[0]->getText());
    $this->assertStringContainsString($creative->getDisplayName(), $rows[1]->getText());
    $this->assertStringContainsString(DoorTarget::Feedback->heading(), $rows[1]->getText());
  }

  /**
   * Tests that a declined project has no suggestions.
   */
  public function testSuggestionsDeclined(): void {

    $project = $this->createProject(ProjectState::Declined);

    $this->drupalLogin($this->admin);
    $this->drupalGet(static::PATH);
    $this->submitForm(['project' => $this->autocomplete($project)], 'Vorschläge zeigen');

    $this->assertSession()->pageTextContains('Für diesen Projektstand gibt es keine Vorschläge.');
  }

}
