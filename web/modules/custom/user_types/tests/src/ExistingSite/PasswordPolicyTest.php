<?php

namespace Drupal\Tests\user_types\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\creatives\Entity\Creative;
use Drupal\Tests\youvo\Traits\RequestTrait;
use Drupal\user\OneTimeAuthentication;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests that passwords chosen by creatives follow the password policies.
 */
#[Group('user_types')]
class PasswordPolicyTest extends ExistingSiteBase {

  use RequestTrait;

  /**
   * A password the policies accept.
   */
  const string VALID = 'Lange-Passphrase-7';

  /**
   * A password below the minimum length.
   */
  const string SHORT = 'Kurz-1';

  /**
   * Tests that the password endpoint refuses a short password.
   */
  public function testUpdateRefusesShortPassword(): void {

    $creative = $this->createCreative();

    $response = $this->patchPassword($creative, static::SHORT);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('new_password', Json::decode((string) $response->getContent())['field']);
    $this->assertTrue($this->checkPassword($creative, 'password'));

    $response = $this->patchPassword($creative, static::VALID);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($this->checkPassword($creative, static::VALID));
  }

  /**
   * Tests that JSON:API cannot change a password past the policies.
   *
   * The password policies check forms and the password endpoint only, so
   * JSON:API may not edit the password field.
   */
  public function testJsonapiCannotChangePassword(): void {

    $creative = $this->createCreative();

    $request = Request::create('/api/users/' . $creative->uuid(), 'PATCH', [], [], [], [], Json::encode([
      'data' => [
        'type' => 'user--user',
        'id' => $creative->uuid(),
        'attributes' => ['pass' => ['value' => static::SHORT, 'existing' => 'password']],
      ],
    ]));
    $request->headers->set('Content-Type', 'application/vnd.api+json');
    $request->headers->set('Accept', 'application/vnd.api+json');
    $this->authenticateRequest($request, $creative);
    $response = $this->doRequest($request);

    $this->assertSame(403, $response->getStatusCode());
    $this->assertTrue($this->checkPassword($creative, 'password'));
  }

  /**
   * Tests that the registration form refuses a short password.
   */
  public function testRegisterRefusesShortPassword(): void {

    $creative = $this->createCreative();
    $timestamp = $this->container->get('datetime.time')->getRequestTime();
    $hash = $this->container->get(OneTimeAuthentication::class)->generateHmac($creative, $timestamp);

    $this->drupalGet('/register/' . $creative->id() . '/' . $timestamp . '/' . $hash);
    $this->assertSession()->fieldExists('password[pass1]');
    $this->assertSession()->buttonExists('Passwort speichern und weiter');
    $this->assertSession()->elementNotExists('css', '.gin-sticky-form-actions');
    // The checklist lists the rules of the creative's password policy.
    $this->assertSession()->elementTextContains('css', '.password-checklist', 'Mindestens 10 Zeichen');
    $page = $this->getSession()->getPage();
    $page->fillField('password[pass1]', static::SHORT);
    $page->fillField('password[pass2]', static::SHORT);
    $page->pressButton('Passwort speichern und weiter');

    $this->assertSession()->pageTextContains('at least 10 characters');
    $this->assertTrue($this->checkPassword($creative, 'password'));
  }

  /**
   * Creates an active creative with the password "password".
   */
  protected function createCreative(): Creative {
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $creative->addRole('creative');
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

  /**
   * Sends a password change for the creative.
   */
  protected function patchPassword(Creative $creative, string $password): Response {
    $request = Request::create('/api/users/update/password?_format=json', 'PATCH', [], [], [], [], Json::encode([
      'current_password' => 'password',
      'new_password' => $password,
    ]));
    $request->headers->set('Content-Type', 'application/json');
    $this->authenticateRequest($request, $creative);
    return $this->doRequest($request);
  }

  /**
   * Checks the stored password of the creative.
   */
  protected function checkPassword(Creative $creative, string $password): bool {
    $stored = $this->container->get('entity_type.manager')->getStorage('user')->loadUnchanged($creative->id());
    return $this->container->get('password')->check($password, $stored->getPassword());
  }

}
