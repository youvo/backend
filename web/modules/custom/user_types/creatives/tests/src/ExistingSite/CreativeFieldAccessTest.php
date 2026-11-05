<?php

namespace Drupal\Tests\creatives\ExistingSite;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\creatives\Entity\Creative;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests who sees the contact and private fields of a creative.
 */
#[Group('creatives')]
class CreativeFieldAccessTest extends ProjectResourceTestBase {

  /**
   * The creative, managers and administrators see the contact data.
   */
  public function testContactFields(): void {

    $creative = $this->createCreative();
    $other = $this->createCreative();
    $manager = $this->createManager();

    foreach (['field_phone', 'mail'] as $field_name) {
      $this->assertTrue($this->viewAccess($creative, $field_name, $creative)->isAllowed(), "$field_name, own");
      $this->assertTrue($this->viewAccess($creative, $field_name, $manager)->isAllowed(), "$field_name, manager");
      $this->assertFalse($this->viewAccess($creative, $field_name, $other)->isAllowed(), "$field_name, other creative");
      $this->assertFalse($this->viewAccess($creative, $field_name, new AnonymousUserSession())->isAllowed(), "$field_name, anonymous");
    }

    $this->assertFalse($creative->get('field_phone')->access('edit', $manager));

    // The result varies by user, so a cached own view is not shown to others.
    $this->assertContains('user', $this->viewAccess($creative, 'field_phone', $other)->getCacheContexts());
  }

  /**
   * Managers see the private fields, other creatives do not.
   *
   * Only the creative edits them.
   */
  public function testPrivateFields(): void {

    $creative = $this->createCreative();
    $other = $this->createCreative();
    $manager = $this->createManager();

    foreach (['field_newsletter', 'field_jobs', 'field_public_profile'] as $field_name) {
      $this->assertTrue($this->viewAccess($creative, $field_name, $creative)->isAllowed(), "$field_name, own");
      $this->assertTrue($this->viewAccess($creative, $field_name, $manager)->isAllowed(), "$field_name, manager");
      $this->assertFalse($this->viewAccess($creative, $field_name, $other)->isAllowed(), "$field_name, other creative");
      $this->assertFalse($this->viewAccess($creative, $field_name, new AnonymousUserSession())->isAllowed(), "$field_name, anonymous");
    }

    $this->assertFalse($creative->get('field_newsletter')->access('edit', $manager));

    // The result varies by user, so a cached own view is not shown to others.
    $this->assertContains('user', $this->viewAccess($creative, 'field_newsletter', $other)->getCacheContexts());
  }

  /**
   * Managers see when a creative last logged in, other creatives do not.
   *
   * The creatives finder lists the last access, so Views checks the field
   * without an entity as well.
   */
  public function testLastAccess(): void {

    $creative = $this->createCreative();
    $other = $this->createCreative();
    $manager = $this->createManager();

    $this->assertTrue($this->viewAccess($creative, 'access', $manager)->isAllowed(), 'manager');
    $this->assertFalse($this->viewAccess($creative, 'access', $other)->isAllowed(), 'other creative');
    $this->assertFalse($this->viewAccess($creative, 'access', new AnonymousUserSession())->isAllowed(), 'anonymous');
    $this->assertFalse($creative->get('access')->access('edit', $manager));

    $definition = $creative->getFieldDefinition('access');
    $handler = $this->container->get('entity_type.manager')->getAccessControlHandler('user');
    $this->assertTrue($handler->fieldAccess('view', $definition, $manager), 'manager, without entity');
    $this->assertFalse($handler->fieldAccess('view', $definition, $other), 'other creative, without entity');
  }

  /**
   * Gets the view access to a field of a creative for an account.
   */
  protected function viewAccess(Creative $creative, string $field_name, AccountInterface $account): AccessResult {
    $result = $creative->get($field_name)->access('view', $account, TRUE);
    $this->assertInstanceOf(AccessResult::class, $result);
    return $result;
  }

}
