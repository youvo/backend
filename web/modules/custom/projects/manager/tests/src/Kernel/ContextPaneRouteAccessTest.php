<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests the access to the context pane route.
 *
 * @group manager
 */
class ContextPaneRouteAccessTest extends MailerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'user_types',
    'views',
    'logbook',
    'manager',
  ];

  /**
   * Only accounts with the general manager access permission get the panes.
   */
  public function testContextPaneRequiresManagerAccess(): void {
    // User 1 bypasses all permission checks: use it up.
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->container->get('router.builder')->rebuild();
    $access = $this->container->get('access_manager');
    $parameters = ['project' => $project->id()];

    $this->assertFalse($access->checkNamedRoute('manager.context_pane', $parameters, User::getAnonymousUser()), 'anonymous');
    $authenticated = $this->accountWith($this->createMailableCreative('Plain'), ['access content']);
    $this->assertFalse($access->checkNamedRoute('manager.context_pane', $parameters, $authenticated), 'access content only');
    $manager = $this->accountWith($this->createMailableCreative('Mgr'), ['general manager access']);
    $this->assertTrue($access->checkNamedRoute('manager.context_pane', $parameters, $manager), 'general manager access');
  }

}
