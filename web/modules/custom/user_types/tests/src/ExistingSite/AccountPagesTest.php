<?php

namespace Drupal\Tests\user_types\ExistingSite;

use Drupal\creatives\Entity\Creative;
use PHPUnit\Framework\Attributes\Group;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the login and password pages that visitors meet signed out.
 *
 * Gin moved their buttons into its sticky bar, which visitors without the
 * admin toolbar never see.
 */
#[Group('user_types')]
class AccountPagesTest extends ExistingSiteBase {

  /**
   * A password the policies accept.
   */
  const string PASSWORD = 'Lange-Passphrase-7';

  /**
   * Tests that the login form keeps its German button below the fields.
   */
  public function testLoginPage(): void {

    $this->drupalGet('/user/login');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextEquals('css', 'h1', 'Anmelden');
    $this->assertSession()->fieldExists('E-Mail-Adresse');
    $this->assertSession()->fieldExists('Passwort');
    $this->assertSession()->buttonExists('Anmelden');
    $this->assertSession()->pageTextNotContains('You can use your email address to login.');
    $this->assertSession()->linkByHrefExists('/user/password');
    $this->assertNoStickyActions();
  }

  /**
   * Tests that staff still land on their dashboard after signing in.
   */
  public function testStaffLoginLeadsToDashboard(): void {

    $manager = Creative::create([
      'name' => $this->randomMachineName(),
      'field_name' => $this->randomMachineName(),
      'mail' => strtolower($this->randomMachineName()) . '@example.com',
      'pass' => static::PASSWORD,
      'status' => 1,
    ]);
    $manager->addRole('creative');
    $manager->addRole('manager');
    $manager->save();
    $this->markEntityForCleanup($manager);

    $this->drupalGet('/user/login');
    $this->submitForm([
      'E-Mail-Adresse' => $manager->getEmail(),
      'Passwort' => static::PASSWORD,
    ], 'Anmelden');

    $this->assertSession()->addressEquals('/admin/dashboard');
  }

  /**
   * Tests that the password page is German and confirms in German.
   */
  public function testPasswordPage(): void {

    $this->drupalGet('/user/password');

    $this->assertSession()->elementTextEquals('css', 'h1', 'Passwort vergessen');
    $this->assertSession()->buttonExists('Link senden');
    $this->assertSession()->pageTextNotContains('Username or email address');
    $this->assertSession()->linkByHrefExists('/user/login');
    $this->assertNoStickyActions();

    // An unknown address sends no mail, but gets the same answer.
    $mail = strtolower($this->randomMachineName()) . '@example.com';
    $this->submitForm(['E-Mail-Adresse' => $mail], 'Link senden');
    $this->assertSession()->pageTextContains('Wenn ' . $mail . ' zu einem Konto gehört, ist eine Mail mit einem Link für ein neues Passwort unterwegs.');
    $this->assertSession()->pageTextNotContains('If ' . $mail . ' is a valid account');
  }

  /**
   * Asserts that Gin left the form actions below the fields.
   */
  protected function assertNoStickyActions(): void {
    $this->assertSession()->elementNotExists('css', 'form.gin--has-sticky-form-actions');
    $this->assertSession()->elementNotExists('css', '.gin-sticky-form-actions');
    $this->assertSession()->elementNotExists('css', '[gin-move-focus-to-sticky-bar]');
  }

}
