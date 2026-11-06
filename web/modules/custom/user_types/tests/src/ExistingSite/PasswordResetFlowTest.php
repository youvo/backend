<?php

namespace Drupal\Tests\user_types\ExistingSite;

use Behat\Mink\Driver\BrowserKitDriver;
use Drupal\creatives\Entity\Creative;
use Drupal\organizations\Entity\Organization;
use Drupal\user\OneTimeAuthentication;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests where the link of the password mail leads.
 *
 * Organizations and creatives set the new password on a public page and go
 * on to the hub or the app; staff keep core's user edit form.
 */
#[Group('user_types')]
class PasswordResetFlowTest extends ExistingSiteBase {

  /**
   * A password the policies accept.
   */
  const string VALID = 'Lange-Passphrase-7';

  /**
   * A password below the minimum length.
   */
  const string SHORT = 'Kurz-1';

  /**
   * The message when the page is opened without a valid token.
   */
  const string INVALID = 'Der Link für ein neues Passwort wurde schon benutzt oder ist nicht mehr gültig.';

  /**
   * Tests that an organization sets the password and goes to the hub.
   */
  public function testOrganizationSetsPassword(): void {

    $organization = Organization::create([
      'name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.org',
      'pass' => 'password',
      'status' => 1,
    ]);
    $organization->addRole('organization');
    $organization->save();
    $this->markEntityForCleanup($organization);

    $this->assertPasswordFlow($organization, 'hub_url');
  }

  /**
   * Tests that a creative sets the password and goes to the app.
   */
  public function testCreativeSetsPassword(): void {
    $this->assertPasswordFlow($this->createCreative(['creative']), 'app_url');
  }

  /**
   * Tests that staff keep core's user edit form.
   */
  public function testStaffKeepEditForm(): void {

    $manager = $this->createCreative(['creative', 'manager']);

    $this->oneTimeLogin($manager);

    $this->assertSession()->addressEquals('/user/' . $manager->id() . '/edit');
    $this->assertStringContainsString('pass-reset-token=', $this->getSession()->getCurrentUrl());
  }

  /**
   * Tests that the page needs the token of the one-time login.
   */
  public function testPageNeedsToken(): void {

    $this->drupalGet('/user/reset/password');
    $this->assertSession()->addressEquals('/user/password');
    $this->assertSession()->pageTextContains(static::INVALID);

    // Signed in by the link, but with another token.
    $creative = $this->createCreative(['creative']);
    $this->oneTimeLogin($creative);
    $this->assertSession()->addressEquals('/user/reset/password');
    $this->drupalGet('/user/reset/password', ['query' => ['pass-reset-token' => 'forged']]);
    $this->assertSession()->addressEquals('/user/password');
    $this->assertSession()->pageTextContains(static::INVALID);
  }

  /**
   * Runs the flow from the one-time login to the frontend login.
   *
   * @param \Drupal\user\UserInterface $user
   *   The organization or creative.
   * @param string $base_key
   *   The youvo.settings key of the frontend the user goes to.
   */
  protected function assertPasswordFlow(UserInterface $user, string $base_key): void {

    $this->oneTimeLogin($user);

    $this->assertSession()->addressEquals('/user/reset/password');
    $this->assertSession()->elementTextEquals('css', 'h1', 'Neues Passwort festlegen');
    $this->assertSession()->elementTextContains('css', '.password-checklist', 'Mindestens 10 Zeichen');
    $this->assertSession()->elementNotExists('css', '.gin-sticky-form-actions');
    $this->assertSession()->pageTextNotContains('You have used a one-time login link.');
    $page_url = $this->getSession()->getCurrentUrl();

    // The policy refuses a short password.
    $this->submitForm([
      'password[pass1]' => static::SHORT,
      'password[pass2]' => static::SHORT,
    ], 'Passwort speichern');
    $this->assertSession()->pageTextContains('at least 10 characters');
    $this->assertTrue($this->checkPassword($user, 'password'));

    // A valid password leads to the frontend login, which opens the
    // dashboard.
    // UiHelperTrait::submitForm() would follow the meta refresh.
    $this->followRedirects(FALSE);
    $page = $this->getSession()->getPage();
    $page->fillField('password[pass1]', static::VALID);
    $page->fillField('password[pass2]', static::VALID);
    $page->pressButton('Passwort speichern');
    $base = rtrim((string) $this->container->get('config.factory')->get('youvo.settings')->get($base_key), '/');
    $login = $base . $this->container->get('config.factory')->get('oauth_grant.settings')->get('auth_success_redirect');
    $this->assertSame($login . '?redirect=%2Fdashboard', $this->getSession()->getResponseHeader('Location'));
    $this->assertTrue($this->checkPassword($user, static::VALID));

    // The token is used up.
    $this->followRedirects(TRUE);
    $this->drupalGet($page_url);
    $this->assertSession()->addressEquals('/user/password');
    $this->assertSession()->pageTextContains(static::INVALID);
  }

  /**
   * Opens the link of the password mail and presses its button.
   */
  protected function oneTimeLogin(UserInterface $user): void {
    $this->drupalGet($this->container->get(OneTimeAuthentication::class)->generateOneTimeLoginUrl($user));
    $this->submitForm([], 'Weiter');
  }

  /**
   * Lets the browser follow redirects, or not, as into the frontends.
   */
  protected function followRedirects(bool $follow): void {
    $driver = $this->getSession()->getDriver();
    $this->assertInstanceOf(BrowserKitDriver::class, $driver);
    $client = $driver->getClient();
    $client->followRedirects($follow);
    $client->followMetaRefresh($follow);
  }

  /**
   * Creates an active creative with the password "password".
   *
   * @param string[] $roles
   *   The roles of the creative.
   */
  protected function createCreative(array $roles): Creative {
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    foreach ($roles as $role) {
      $creative->addRole($role);
    }
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Checks the stored password of a user.
   */
  protected function checkPassword(UserInterface $user, string $password): bool {
    $stored = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($user->id());
    return $this->container->get('password')->check($password, $stored->getPassword());
  }

}
