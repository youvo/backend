<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the public access to projects, the hidden flag and the query access.
 */
#[Group('projects')]
class ProjectPublicAccessTest extends ProjectResourceTestBase {

  /**
   * Tests the view access of anonymous and logged-in users per state.
   */
  public function testViewAccessPerState(): void {
    $anonymous = new AnonymousUserSession();
    $creative = $this->createCreative();
    $expected = [
      // State => [anonymous, anonymous when hidden, logged in when hidden].
      'open' => [TRUE, FALSE, TRUE],
      'ongoing' => [FALSE, FALSE, TRUE],
      'completed' => [TRUE, FALSE, TRUE],
      'draft' => [FALSE, FALSE, FALSE],
      'pending' => [FALSE, FALSE, FALSE],
    ];
    foreach ($expected as $state => [$public, $public_hidden, $logged_in_hidden]) {
      $project = $this->createProject(ProjectState::from($state));
      $this->assertSame($public, $this->viewAccess($project, $anonymous), "anonymous, $state");
      $project->setHidden()->save();
      $this->assertSame($public_hidden, $this->viewAccess($project, $anonymous), "anonymous, hidden $state");
      $this->assertSame($logged_in_hidden, $this->viewAccess($project, $creative), "logged in, hidden $state");
      // The organization always views its project.
      $this->assertTrue($this->viewAccess($project, $project->getOwner()), "organization, hidden $state");
    }
  }

  /**
   * Tests that only logged-in users view the contact person.
   */
  public function testContactFieldAccess(): void {
    $project = $this->createProject(ProjectState::Open);
    $this->assertFalse($project->get('field_contact')->access('view', new AnonymousUserSession()));
    $this->assertTrue($project->get('field_contact')->access('view', $this->createCreative()));
  }

  /**
   * Tests the access-checked project queries per account.
   */
  public function testQueryAccess(): void {

    $visible = $this->createProject(ProjectState::Open);
    $hidden = $this->createProject(ProjectState::Open);
    $hidden->setHidden()->save();
    $archived = $this->createProject(ProjectState::Open);
    $archived->setArchived()->save();
    $ids = [(int) $visible->id(), (int) $hidden->id(), (int) $archived->id()];

    $this->assertSame([(int) $visible->id()], $this->query($ids, $this->createCreative()));
    // The organization lists its own hidden project.
    $this->assertSame([(int) $hidden->id()], $this->query([(int) $hidden->id()], $hidden->getOwner()));
    // Managers list hidden projects, but no archived ones.
    $this->assertSame([(int) $visible->id(), (int) $hidden->id()], $this->query($ids, $this->createManager()));
    // Supervisors list all.
    $this->assertSame($ids, $this->query($ids, $this->createSupervisor()));

    // Cached results vary per permissions and, for the own projects, per user.
    $conditions = $this->container->get('entity_type.manager')->getHandler('project', 'query_access')
      ->getConditions('view', $this->createCreative());
    $this->assertContains('user.permissions', $conditions->getCacheContexts());
    $this->assertContains('user', $conditions->getCacheContexts());
  }

  /**
   * Checks the view access of an account, without the static cache.
   */
  protected function viewAccess(ProjectInterface $project, AccountInterface $account): bool {
    $this->container->get('entity_type.manager')->getAccessControlHandler('project')->resetCache();
    return $project->access('view', $account);
  }

  /**
   * Runs an access-checked project query among the given ids as an account.
   *
   * @return int[]
   *   The ids the account may list, ascending.
   */
  protected function query(array $ids, AccountInterface $account): array {
    $switcher = $this->container->get('account_switcher');
    $switcher->switchTo($account);
    try {
      $result = $this->container->get('entity_type.manager')->getStorage('project')->getQuery()
        ->accessCheck(TRUE)
        ->condition('id', $ids, 'IN')
        ->sort('id')
        ->execute();
    }
    finally {
      $switcher->switchBack();
    }
    return array_map('intval', array_values($result));
  }

}
