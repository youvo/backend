<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the access to the context pane route.
 *
 * The pane tests call the controller directly, so the route access is
 * checked here.
 */
#[Group('manager')]
class ManagerRouteAccessTest extends ManagerExistingSiteTestBase {

  /**
   * Only accounts with the general manager access permission get the panes.
   */
  public function testContextPaneRequiresManagerAccess(): void {
    $project = $this->createProject(ProjectState::Draft);
    $access = $this->container->get('access_manager');
    $parameters = ['project' => $project->id()];
    $accounts = [
      'anonymous' => [new AnonymousUserSession(), FALSE],
      'creative' => [$this->createCreative(), FALSE],
      'organization' => [$project->getOwner(), FALSE],
      'manager of another organization' => [$this->createManager(), TRUE],
      'manager' => [$this->managerOf($project), TRUE],
      'supervisor' => [$this->createSupervisor(), TRUE],
    ];
    foreach ($accounts as $label => [$account, $expected]) {
      $this->assertSame($expected, $access->checkNamedRoute('manager.context_pane', $parameters, $account), $label);
    }
  }

}
