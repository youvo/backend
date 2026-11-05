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
use Drupal\Tests\youvo\Traits\MailInterceptTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the mail door page.
 *
 * Keeps the logbook listeners, because the door writes project_follow logs.
 */
#[Group('mailer')]
class MailDoorTest extends ExistingSiteBase {

  use MailInterceptTrait;

  /**
   * The button text of the door.
   */
  const string BUTTON = 'Weiter zu youvo';

  /**
   * The correspondence UUIDs of the doors built in the test.
   *
   * @var string[]
   */
  protected array $correspondences = [];

  /**
   * The IDs of the projects created in the test.
   *
   * @var int[]
   */
  protected array $projects = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->interceptMails();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {

    $keys = [];
    foreach ($this->correspondences as $uuid) {
      foreach (DoorTarget::cases() as $target) {
        $keys[] = $uuid . ':' . $target->value;
      }
    }
    $this->container->get('keyvalue.expirable')->get('mailer.door_follow')->deleteMultiple($keys);

    // The logbook listeners stay, so every log of the projects is removed.
    if ($this->projects) {
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
  protected function createProject(ProjectState $state = ProjectState::Open): ProjectInterface {

    $manager = $this->createCreative();
    $manager->addRole('manager');
    $manager->save();

    $organization = $this->createOrganization();
    $organization->set('field_manager', $manager)->save();

    $project = Project::create([
      'uid' => $organization->id(),
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
   * Records a mail of a project and builds a door of it.
   *
   * @return string
   *   The path of the door.
   */
  protected function door(UserInterface $recipient, ProjectInterface $project, DoorTarget $target, ?int $timestamp = NULL, string $subject = ''): string {

    $uuid = $this->container->get('uuid')->generate();
    // The project deletes its correspondence.
    Correspondence::create([
      'type' => 'mail',
      'uuid' => $uuid,
      'project' => $project->id(),
      'subject' => 'Erinnerung',
      'body' => ['value' => '<p>m</p>', 'format' => 'email_html'],
      'subtype' => 'mailer.manager.reminder_open',
      'origin' => 'manager',
      'result' => 'sent',
    ])->save();
    $this->correspondences[] = $uuid;

    // In the past, so that a login by the door is newer than the door.
    $timestamp ??= time() - 120;
    $url = $this->doorLinks()->build($recipient, $uuid, $target, $subject, $timestamp);
    return (string) parse_url($url, PHP_URL_PATH);
  }

  /**
   * Gets the door links service.
   */
  protected function doorLinks(): DoorLinks {
    return $this->container->get(DoorLinks::class);
  }

  /**
   * Stops the browser from following redirects into the hub.
   *
   * A redirect response also carries a meta refresh in its body.
   */
  protected function stayOnSite(): void {
    $driver = $this->getSession()->getDriver();
    $this->assertInstanceOf(BrowserKitDriver::class, $driver);
    $client = $driver->getClient();
    $client->followRedirects(FALSE);
    $client->followMetaRefresh(FALSE);
  }

  /**
   * Sends a header with the following requests of the browser.
   */
  protected function setRequestHeader(string $name, string $value): void {
    $driver = $this->getSession()->getDriver();
    $this->assertInstanceOf(BrowserKitDriver::class, $driver);
    $driver->getClient()->setServerParameter($name, $value);
  }

  /**
   * Opens a door without following redirects.
   */
  protected function visitDoor(string $path): void {
    $this->stayOnSite();
    $this->drupalGet($path);
  }

  /**
   * Presses the button of the open door without following redirects.
   */
  protected function press(): void {
    $this->stayOnSite();
    $this->getSession()->getPage()->pressButton(static::BUTTON);
  }

  /**
   * Opens a door and presses its button without following redirects.
   */
  protected function pressDoor(string $path): void {
    $this->visitDoor($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->press();
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
   * Tests that opening a door does nothing.
   */
  public function testGetDoesNothing(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);

    $this->visitDoor($path);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Neue Bewerbungen für euer Projekt');
    $this->assertSession()->pageTextContains($project->getTitle());
    $this->assertSession()->pageTextContains('Ihr werdet dabei angemeldet.');
    $this->assertSession()->buttonExists(static::BUTTON);
    $this->assertCount(0, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($organization));
  }

  /**
   * Tests that pressing the button logs in, follows and opens the hub.
   */
  public function testPressLogsInAndFollows(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);

    $this->pressDoor($path);

    $follows = $this->follows($project);
    $this->assertCount(1, $follows);
    $this->assertSame('applicants', $follows[0]->getMisc()['target']);
    $this->assertSame((int) $organization->id(), (int) $follows[0]->getOwnerId());
    // Core marks the first redirect after a login.
    $this->assertSame($this->doorLinks()->loginUrl() . '?check_logged_in=1', $this->location());
    $this->assertGreaterThan(0, $this->lastLogin($organization));
  }

  /**
   * Tests that a door follows once and logs in once.
   */
  public function testSecondPressLogsOnce(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);

    $this->pressDoor($path);
    $login = $this->lastLogin($organization);
    $this->getSession()->reset();
    $this->visitDoor($path);
    $this->assertSession()->pageTextNotContains('Ihr werdet dabei angemeldet.');
    $this->press();

    $this->assertCount(1, $this->follows($project));
    $this->assertSame($login, $this->lastLogin($organization));
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a door after another login still follows, without login.
   */
  public function testDoorAfterOtherLoginStillFollows(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants, time() - 120);
    $login = time() - 60;
    $organization->setLastLoginTime($login)->save();

    $this->pressDoor($path);

    $this->assertCount(1, $this->follows($project));
    $this->assertSame($login, $this->lastLogin($organization), 'The door does not log in.');
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a draft door resolves the target when the button is pressed.
   */
  public function testDraftDoorAfterSubmit(): void {

    $project = $this->createProject(ProjectState::Draft);
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Draft);
    $organization->setLastLoginTime(time() - 60)->save();
    $project->set('field_lifecycle', ProjectState::Pending->value)->save();

    $this->pressDoor($path);

    $this->assertCount(1, $this->follows($project));
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that an expired door leads to the target without login and log.
   */
  public function testExpiredDoor(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants, time() - DoorLinks::LIFETIME - 60);

    $this->visitDoor($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Ihr werdet dabei angemeldet.');
    $this->press();

    $this->assertCount(0, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($organization));
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a changed door is denied.
   */
  public function testTamperedDoor(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);

    $this->visitDoor(str_replace('/applicants/', '/complete/', $path));

    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->pageTextNotContains($project->getTitle());
    $this->assertCount(0, $this->follows($project));
  }

  /**
   * Tests that a door of a blocked account is denied.
   */
  public function testBlockedRecipient(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);
    $organization->block()->save();

    $this->visitDoor($path);

    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->pageTextNotContains($project->getTitle());
    $this->assertCount(0, $this->follows($project));
  }

  /**
   * Tests that a door of staff neither logs in nor follows.
   */
  public function testStaffRecipient(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $organization->addRole('manager');
    $organization->save();
    $path = $this->door($organization, $project, DoorTarget::Applicants);

    $this->pressDoor($path);

    $this->assertCount(0, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($organization));
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a door does not switch a signed-in account.
   */
  public function testOtherUserSignedIn(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);
    $other = $this->createOrganization();
    $this->drupalLogin($other);

    $this->visitDoor($path);
    $this->assertSession()->pageTextContains('Der Link wurde für ein anderes Konto verschickt.');
    $this->assertSession()->pageTextNotContains('Ihr werdet dabei angemeldet.');
    $this->press();

    $this->assertCount(0, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($organization));
    $this->assertTrue($this->drupalUserIsLoggedIn($other));
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a door of the signed-in recipient follows without login.
   */
  public function testRecipientSignedIn(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $first = $this->door($organization, $project, DoorTarget::Applicants);
    $second = $this->door($organization, $project, DoorTarget::Applicants);

    $this->pressDoor($first);
    $login = $this->lastLogin($organization);
    $this->pressDoor($second);

    $this->assertCount(2, $this->follows($project));
    $this->assertSame($login, $this->lastLogin($organization));
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a press from another site follows without login.
   */
  public function testForeignOriginDoesNotLogIn(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);

    $this->visitDoor($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->setRequestHeader('HTTP_ORIGIN', 'https://evil.example');
    $this->press();

    $this->assertCount(1, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($organization));
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a cross-site press follows without login.
   */
  public function testCrossSiteFetchDoesNotLogIn(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);

    $this->visitDoor($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->setRequestHeader('HTTP_SEC_FETCH_SITE', 'cross-site');
    $this->press();

    $this->assertCount(1, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($organization));
    $this->assertSame($this->container->get('mailer.hub_urls')->base() . '/projects/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a door of a former owner neither logs in nor follows.
   */
  public function testRecipientNoLongerOnProject(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $path = $this->door($organization, $project, DoorTarget::Applicants);
    $project->setOwner($this->createOrganization())->save();

    $this->pressDoor($path);

    $this->assertCount(0, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($organization));
  }

  /**
   * Tests that a participant follows a feedback door without login.
   */
  public function testFeedbackDoorOfParticipant(): void {

    $project = $this->createProject(ProjectState::Completed);
    $creative = $this->createCreative();
    $project->appendParticipant($creative)->save();
    $path = $this->door($creative, $project, DoorTarget::Feedback);

    $this->visitDoor($path);
    $this->assertSession()->pageTextContains('Wie war euer Projekt?');
    $this->assertSession()->pageTextNotContains('Ihr werdet dabei angemeldet.');
    $this->press();

    $this->assertCount(1, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($creative));
    $website_url = rtrim((string) $this->container->get('config.factory')->get('youvo.settings')->get('website_url'), '/');
    $this->assertSame($website_url . '/feedback/creative/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a feedback door never logs in and sends to the feedback page.
   */
  public function testFeedbackDoorNeverLogsIn(): void {

    $project = $this->createProject(ProjectState::Completed);
    $creative = $this->createCreative();
    $project->appendParticipant($creative)->save();
    $path = $this->door($creative, $project, DoorTarget::Feedback);

    $this->pressDoor($path);

    $follows = $this->follows($project);
    $this->assertCount(1, $follows);
    $this->assertSame((int) $creative->id(), (int) $follows[0]->getOwnerId());
    $this->assertSame(0, $this->lastLogin($creative));
    $this->assertFalse($this->drupalUserIsLoggedIn($creative));
    $website_url = rtrim((string) $this->container->get('config.factory')->get('youvo.settings')->get('website_url'), '/');
    $this->assertSame($website_url . '/feedback/creative/' . $project->uuid(), $this->location());
  }

  /**
   * Tests that a feedback door of a removed participant gives no log.
   */
  public function testFeedbackDoorOfRemovedParticipant(): void {

    $project = $this->createProject(ProjectState::Completed);
    $creative = $this->createCreative();
    $project->appendParticipant($creative)->save();
    $path = $this->door($creative, $project, DoorTarget::Feedback);
    $project->setParticipants([])->save();

    $this->pressDoor($path);

    $this->assertCount(0, $this->follows($project));
    $this->assertSame(0, $this->lastLogin($creative));
  }

  /**
   * Tests that a door with a subject opens.
   */
  public function testApplicantDoorWithSubject(): void {

    $project = $this->createProject();
    $creative = $this->createCreative();
    $path = $this->door($project->getOwner(), $project, DoorTarget::Applicant, NULL, $creative->uuid());
    $this->assertStringEndsWith('/' . $creative->uuid(), $path);

    $this->visitDoor($path);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Neue Bewerbung für euer Projekt');
  }

}
