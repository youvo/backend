<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\manager\Plugin\Block\ManagerProjectsBlock;
use Drupal\projects\ProjectState;
use Drupal\user\UserInterface;

/**
 * Tests the block with the projects of a manager.
 *
 * @group manager
 */
class ManagerProjectsBlockTest extends ProjectSeenKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * Renders the block for a user.
   */
  protected function renderFor(UserInterface $user): string {
    $user = $this->accountWith($user, ['general manager access']);
    $this->setCurrentUser($user);
    $block = $this->container->get('plugin.manager.block')->createInstance('manager_projects');
    $this->assertTrue($block->access($user));
    $build = $block->build();
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Only users with the manager permission may see the block.
   */
  public function testBlockAccess(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $block = $this->container->get('plugin.manager.block')->createInstance('manager_projects');
    $this->assertFalse($block->access($this->accountWith($manager, ['access content'])));
    $this->assertTrue($block->access($this->accountWith($manager, ['general manager access'])));
  }

  /**
   * The block shows the own unfinished projects with the badge.
   */
  public function testShowsOwnProjectsWithBadge(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $project->setTitle('Garden website');
    $project->save();
    $this->createProjectFor($project->getOwner(), ProjectState::Completed, 'Finished one');
    $foreign = $this->createMailableProject(ProjectState::Open);
    $foreign->setTitle('Foreign project');
    $foreign->save();
    $this->createProjectLog($project);
    $this->createProjectLog($project);
    $html = $this->renderFor($manager);
    $this->assertStringContainsString('Garden website', $html);
    $this->assertStringContainsString('2 new', $html);
    $this->assertStringNotContainsString('Finished one', $html);
    $this->assertStringNotContainsString('Foreign project', $html);
    // The confirm button is an icon; its name says which project it confirms.
    $this->assertStringContainsString('manager-projects__check', $html);
    $this->assertStringContainsString('aria-label="Mark Garden website as seen"', $html);
    $this->assertStringContainsString('manager-projects__state', $html);
  }

  /**
   * The card shows the name of the organization, not its email address.
   */
  public function testShowsTheOrganizationName(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $project->getOwner()->set('field_name', 'Greenhouse e.V.')->save();
    $html = $this->renderFor($this->managerOf($project));
    $this->assertStringContainsString('Greenhouse e.V.', $html);
    $this->assertStringNotContainsString('organization@example.com', $html);
  }

  /**
   * A project with more events than the latest ones has a toggle for all.
   */
  public function testToggleForAllEvents(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $project->setTitle('Garden website');
    $project->save();
    $few = $this->createProjectFor($project->getOwner(), ProjectState::Open, 'Quiet project');
    for ($i = 0; $i < 5; $i++) {
      $this->createProjectLog($project);
    }
    $this->createProjectLog($few);
    $html = $this->renderFor($this->managerOf($project));
    $this->assertSame(1, substr_count($html, '<details class="manager-projects__all-events"'), 'Only the project with more than the shown events.');
    $this->assertStringContainsString('All events (5)', $html);
    $this->assertMatchesRegularExpression('#data-events-url="/project/manage/events/' . $project->id() . '"#', $html);
    // Without JavaScript the toggle leads to the logbook.
    $this->assertStringContainsString('/logbook?search=Garden%20website', $html);
  }

  /**
   * A confirmed project has no badge and no button.
   */
  public function testConfirmedProjectHasNoBadge(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->createProjectLog($project);
    $this->container->get('manager.project_seen')->markSeen($manager, $project);
    $html = $this->renderFor($manager);
    $this->assertStringNotContainsString('manager-projects__badge', $html);
    $this->assertStringNotContainsString('manager-projects__check"', $html);
  }

  /**
   * Names with markup are escaped.
   */
  public function testNamesAreEscaped(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $project->setTitle('<script>alert(1)</script>');
    $project->save();
    $html = $this->renderFor($this->managerOf($project));
    $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
  }

  /**
   * More than the limit gives the limit and a link to the overview.
   */
  public function testLimitAndLinkToTheOverview(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    for ($i = 1; $i <= ManagerProjectsBlock::LIMIT; $i++) {
      $this->createProjectFor($project->getOwner(), ProjectState::Open, 'Extra ' . $i);
    }
    $html = $this->renderFor($manager);
    $this->assertSame(ManagerProjectsBlock::LIMIT, substr_count($html, 'class="manager-projects__card'));
    $this->assertStringContainsString('/projects/manage', $html);
  }

  /**
   * A user who manages nothing sees the empty text.
   */
  public function testEmptyBlock(): void {
    $creative = $this->createMailableCreative('Nobody');
    $html = $this->renderFor($creative);
    $this->assertStringContainsString('No running projects', $html);
    $this->assertStringNotContainsString('manager-projects__card', $html);
  }

  /**
   * The block is cached per user and invalidated by a confirmation.
   */
  public function testCacheability(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $account = $this->accountWith($manager, ['general manager access']);
    $this->setCurrentUser($account);
    $block = $this->container->get('plugin.manager.block')->createInstance('manager_projects');
    $build = $block->build();
    $this->assertContains('user', $build['#cache']['contexts']);
    // The buttons carry a CSRF token, which belongs to the session.
    $this->assertContains('session', $build['#cache']['contexts']);
    $this->assertContains('manager_project_seen:' . $manager->id(), $build['#cache']['tags']);
    $this->assertContains('log_list', $build['#cache']['tags']);
  }

}
