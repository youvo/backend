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
use Drupal\Tests\youvo\Traits\RequestTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the invitation of prospects through the mail door.
 *
 * Keeps the logbook listeners, because the door writes project_follow logs.
 */
#[Group('mailer')]
class MailDoorInviteTest extends ExistingSiteBase {

  use MailInterceptTrait;
  use RequestTrait;

  /**
   * The button text of the password form.
   */
  const string BUTTON = 'Passwort speichern und weiter';

  /**
   * The password set in the tests.
   */
  const string PASSWORD = 'Door-Password-1';

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
   * Creates a draft project of a prospect with a manager.
   */
  protected function createProject(ProjectState $state = ProjectState::Draft): ProjectInterface {

    $manager = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);

    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.org',
      'field_manager' => $manager,
      'status' => 1,
    ]);
    $organization->addRole(Organization::ROLE_PROSPECT);
    $organization->save();
    $this->markEntityForCleanup($organization);

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
   * Records an invite mail of a project and builds its draft door.
   *
   * @return string
   *   The path of the door.
   */
  protected function door(ProjectInterface $project, ?int $timestamp = NULL): string {

    $uuid = $this->container->get('uuid')->generate();
    // The project deletes its correspondence.
    Correspondence::create([
      'type' => 'mail',
      'uuid' => $uuid,
      'project' => $project->id(),
      'subject' => 'Einladung',
      'body' => ['value' => '<p>m</p>', 'format' => 'email_html'],
      'subtype' => 'mailer.project_action.invite_organization',
      'origin' => 'automatic',
      'result' => 'sent',
    ])->save();
    $this->correspondences[] = $uuid;

    // In the past, so that a login by the door is newer than the door.
    $timestamp ??= time() - 120;
    $url = $this->doorLinks()->build($project->getOwner(), $uuid, DoorTarget::Draft, '', $timestamp);
    return (string) parse_url($url, PHP_URL_PATH);
  }

  /**
   * Gets the door links service.
   */
  protected function doorLinks(): DoorLinks {
    return $this->container->get(DoorLinks::class);
  }

  /**
   * Opens a door without following redirects into the hub.
   *
   * A redirect response also carries a meta refresh in its body.
   */
  protected function visitDoor(string $path): void {
    $driver = $this->getSession()->getDriver();
    $this->assertInstanceOf(BrowserKitDriver::class, $driver);
    $client = $driver->getClient();
    $client->followRedirects(FALSE);
    $client->followMetaRefresh(FALSE);
    $this->drupalGet($path);
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
   * Submits the password form of the open door.
   */
  protected function submitPassword(string $password): void {
    $page = $this->getSession()->getPage();
    $page->fillField('password[pass1]', $password);
    $page->fillField('password[pass2]', $password);
    $page->pressButton(static::BUTTON);
  }

  /**
   * Reloads an account from the database.
   */
  protected function reload(UserInterface $account): UserInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $storage->resetCache([$account->id()]);
    return $storage->load($account->id());
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
   * Asserts that the prospect is unchanged and the follows were logged.
   */
  protected function assertUntouched(ProjectInterface $project, int $follows = 0): void {
    $organization = $this->reload($project->getOwner());
    $this->assertInstanceOf(Organization::class, $organization);
    $this->assertTrue($organization->hasRoleProspect());
    $this->assertFalse($organization->hasRoleOrganization());
    $this->assertEmpty($organization->getPassword());
    $this->assertSame(0, (int) $organization->getLastLoginTime());
    $this->assertCount($follows, $this->follows($project));
  }

  /**
   * Tests that the manager invites a prospect with a draft door.
   */
  public function testInviteMailCarriesDoor(): void {

    $project = $this->createProject();
    $organization = $project->getOwner();
    $manager = $organization->getManager();

    $request = Request::create('/api/projects/' . $project->uuid() . '/notify', 'POST');
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $manager);
    $response = $this->doRequest($request);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('"Project invited."', $response->getContent());

    $this->assertCount(1, $this->interceptedMails);
    $this->assertSame([$organization->getEmail()], $this->interceptedMails[0]['to']);
    $this->assertStringContainsString('/door/' . $organization->id() . '/', $this->interceptedMails[0]['html']);
    $this->assertMatchesRegularExpression('#/door/' . $organization->id() . '/[0-9a-f-]{36}/draft/#', $this->interceptedMails[0]['html']);
    $this->assertStringNotContainsString('/invite/', $this->interceptedMails[0]['html']);

    $ids = $this->container->get('entity_type.manager')->getStorage('correspondence')->getQuery()
      ->accessCheck(FALSE)
      ->condition('project', $project->id())
      ->condition('subtype', 'mailer.project_action.invite_organization')
      ->execute();
    $this->assertCount(1, $ids);
    $entry = Correspondence::load(reset($ids));
    $this->correspondences[] = $entry->uuid();
    $body = $entry->get('body')->value;
    $this->assertStringContainsString('[Link entfernt]', $body);
    $this->assertStringNotContainsString('/door/', $body);
  }

  /**
   * Tests that the prospect sets a password, is promoted and follows.
   */
  public function testProspectSetsPasswordAndFollows(): void {

    $project = $this->createProject();
    $path = $this->door($project);

    $this->visitDoor($path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('password[pass1]');
    $this->assertSession()->fieldExists('password[pass2]');
    $this->assertSession()->pageTextContains($project->getTitle());
    $this->assertCount(0, $this->follows($project));
    $this->submitPassword(static::PASSWORD);

    $organization = $this->reload($project->getOwner());
    $this->assertInstanceOf(Organization::class, $organization);
    $this->assertFalse($organization->hasRoleProspect());
    $this->assertTrue($organization->hasRoleOrganization());
    $this->assertTrue($this->container->get('password')->check(static::PASSWORD, $organization->getPassword()));
    $this->assertGreaterThan(0, (int) $organization->getLastLoginTime());
    $follows = $this->follows($project);
    $this->assertCount(1, $follows);
    $this->assertSame('draft', $follows[0]->getMisc()['target']);
    $this->assertSame((int) $organization->id(), (int) $follows[0]->getOwnerId());
    // Core marks the first redirect after a login.
    $this->assertSame($this->doorLinks()->loginUrl() . '?check_logged_in=1', $this->getSession()->getResponseHeader('Location'));

    // The new password spends the door.
    $this->getSession()->reset();
    $this->visitDoor($path);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that the password is required.
   */
  public function testProspectWithoutPassword(): void {

    $project = $this->createProject();
    $this->visitDoor($this->door($project));
    $this->submitPassword('');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '[data-drupal-messages] [role="alert"], .messages--error');
    $this->assertSession()->fieldExists('password[pass1]');
    $this->assertUntouched($project);
  }

  /**
   * Tests that the door names the password rules and refuses a short password.
   */
  public function testShortPasswordIsRefused(): void {

    $project = $this->createProject();
    $this->visitDoor($this->door($project));
    $this->assertSession()->pageTextContains('Mindestens 10 Zeichen, ohne eure E-Mail-Adresse.');
    $this->submitPassword('Kurz-1');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('at least 10 characters');
    $this->assertSession()->fieldExists('password[pass1]');
    $this->assertUntouched($project);
  }

  /**
   * Tests that a password form posted from another site changes nothing.
   */
  public function testCrossSitePasswordChangesNothing(): void {

    $project = $this->createProject();
    $this->visitDoor($this->door($project));
    $this->setRequestHeader('HTTP_ORIGIN', 'https://evil.example');
    $this->submitPassword(static::PASSWORD);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertUntouched($project);
  }

  /**
   * Tests that a cross-site password form without an origin changes nothing.
   */
  public function testCrossSiteFetchPasswordChangesNothing(): void {

    $project = $this->createProject();
    $this->visitDoor($this->door($project));
    $this->setRequestHeader('HTTP_SEC_FETCH_SITE', 'cross-site');
    $this->submitPassword(static::PASSWORD);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertUntouched($project);
  }

  /**
   * Tests that an expired door offers no password and leads to the login.
   */
  public function testExpiredProspectDoor(): void {

    $project = $this->createProject();
    $this->visitDoor($this->door($project, time() - DoorLinks::LIFETIME - 60));

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('password[pass1]');
    $this->assertSession()->pageTextContains('Der Link ist abgelaufen');
    $this->getSession()->getPage()->pressButton('Weiter zu youvo');

    $this->assertSame($this->doorLinks()->loginUrl(), $this->getSession()->getResponseHeader('Location'));
    $this->assertUntouched($project);
  }

  /**
   * Tests that a prospect without a draft gets no password form.
   */
  public function testDeclinedProspectDoor(): void {

    $project = $this->createProject();
    $path = $this->door($project);
    $project->set('field_lifecycle', ProjectState::Declined->value)->save();

    $this->visitDoor($path);

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('password[pass1]');
    $this->assertSession()->buttonNotExists(static::BUTTON);
  }

  /**
   * Tests that the door is checked again when the password is submitted.
   *
   * The door is still valid, so the press is logged as a follow.
   */
  public function testDeclinedBetweenOpenAndSubmit(): void {

    $project = $this->createProject();
    $this->visitDoor($this->door($project));
    $this->assertSession()->fieldExists('password[pass1]');
    $project->set('field_lifecycle', ProjectState::Declined->value)->save();
    $this->submitPassword(static::PASSWORD);

    $this->assertSame($this->doorLinks()->loginUrl(), $this->getSession()->getResponseHeader('Location'));
    $this->assertUntouched($project, 1);
  }

  /**
   * Tests that the old invite routes are gone.
   *
   * The blocker mode answers every unknown path with 403, so the router is
   * asked instead of the site.
   */
  public function testOldInviteRouteGone(): void {
    $route_provider = $this->container->get('router.route_provider');
    $this->assertCount(0, $route_provider->getRoutesByPattern('/invite/1/1/x'));
    $this->assertCount(0, $route_provider->getRoutesByPattern('/invite/password/1'));
  }

}
