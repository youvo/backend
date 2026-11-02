<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\manager\Plugin\Block\ManagerProjectsBlock;
use Drupal\projects\ProjectState;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the block with the projects of a manager.
 */
#[Group('manager')]
class ManagerProjectsBlockTest extends ManagerExistingSiteTestBase {

  /**
   * Creates the block.
   */
  protected function block(): ManagerProjectsBlock {
    $block = $this->container->get('plugin.manager.block')->createInstance('manager_projects');
    $this->assertInstanceOf(ManagerProjectsBlock::class, $block);
    return $block;
  }

  /**
   * Renders the block for a user.
   */
  protected function renderFor(UserInterface $user): string {
    $this->actAs($user);
    $block = $this->block();
    $this->assertTrue($block->access($user));
    $build = $block->build();
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Only users with the manager permission may see the block.
   */
  public function testBlockAccess(): void {
    $this->assertTrue($this->block()->access($this->createManager()));
    $this->assertTrue($this->block()->access($this->createSupervisor()));
    $this->assertFalse($this->block()->access($this->createCreative()));
  }

  /**
   * The block shows the own unfinished projects with the badge.
   */
  public function testShowsOwnProjectsWithBadge(): void {
    $project = $this->createProject(ProjectState::Open, NULL, 'Garden website');
    $this->createProject(ProjectState::Completed, $project->getOwner(), 'Finished one');
    $this->createProject(ProjectState::Open, NULL, 'Foreign project');
    $this->createLog($project);
    $this->createLog($project);
    $html = $this->renderFor($this->managerOf($project));
    $this->assertStringContainsString('Garden website', $html);
    $this->assertStringContainsString('2 new', $html);
    $this->assertStringNotContainsString('Finished one', $html);
    $this->assertStringNotContainsString('Foreign project', $html);
    // The confirm button is an icon; its name says which project it confirms.
    $this->assertStringContainsString('aria-label="Mark Garden website as seen"', $html);
    $this->assertStringContainsString('/project/manage/seen/' . $project->id(), $html);
    $this->assertStringContainsString('manager-projects__state', $html);
    // The title leads to the project manager.
    $this->assertStringContainsString('<a href="/projects/manage">Garden website</a>', $html);
  }

  /**
   * The card shows the name of the organization, not its email address.
   */
  public function testShowsTheOrganizationName(): void {
    $project = $this->createProject(ProjectState::Open);
    $organization = $project->getOwner();
    $organization->set('field_name', 'Greenhouse e.V.')->save();
    $html = $this->renderFor($this->managerOf($project));
    $this->assertStringContainsString('Greenhouse e.V.', $html);
    $this->assertStringNotContainsString($organization->getEmail(), $html);
  }

  /**
   * Events are only reachable through the dropdown of a project with events.
   */
  public function testEventsOnlyInTheDropdown(): void {
    $project = $this->createProject(ProjectState::Open, NULL, 'Garden website');
    $quiet = $this->createProject(ProjectState::Open, $project->getOwner(), 'Quiet project');
    $this->createLog($project);
    $html = $this->renderFor($this->managerOf($project));
    $this->assertSame(1, substr_count($html, '<details class="manager-projects__all-events"'), 'Only the project with events.');
    $this->assertStringContainsString('Show all events', $html);
    $this->assertStringNotContainsString('manager-projects__log"', $html);
    $this->assertStringContainsString('data-events-url="/project/manage/events/' . $project->id() . '"', $html);
    $this->assertStringNotContainsString('/project/manage/events/' . $quiet->id(), $html);
    // Without JavaScript the toggle leads to the logbook.
    $this->assertStringContainsString('/logbook?search=Garden%20website', $html);
  }

  /**
   * A confirmed project has no badge and no button.
   */
  public function testConfirmedProjectHasNoBadge(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->createLog($project);
    $this->container->get('manager.project_seen')->markSeen($manager, $project);
    $html = $this->renderFor($manager);
    $this->assertStringContainsString('manager-projects__card', $html);
    $this->assertStringNotContainsString('manager-projects__badge', $html);
    $this->assertStringNotContainsString('manager-projects__check"', $html);
    $this->assertStringNotContainsString('/project/manage/seen', $html);
  }

  /**
   * More than the limit gives the limit and a link to the overview.
   */
  public function testLimitAndLinkToTheOverview(): void {
    $project = $this->createProject(ProjectState::Open);
    for ($i = 1; $i <= ManagerProjectsBlock::LIMIT; $i++) {
      $this->createProject(ProjectState::Open, $project->getOwner(), 'Extra ' . $i);
    }
    $html = $this->renderFor($this->managerOf($project));
    $this->assertSame(ManagerProjectsBlock::LIMIT, substr_count($html, 'class="manager-projects__card'));
    $this->assertStringContainsString('manager-projects__more', $html);
  }

  /**
   * A user who manages nothing sees the empty text.
   */
  public function testEmptyBlock(): void {
    $html = $this->renderFor($this->createManager());
    $this->assertStringContainsString('No running projects', $html);
    $this->assertStringNotContainsString('manager-projects__card', $html);
  }

  /**
   * The block is cached per user and session and invalidated by a confirmation.
   */
  public function testCacheability(): void {
    $project = $this->createProject(ProjectState::Open);
    $manager = $this->managerOf($project);
    $this->actAs($manager);
    $build = $this->block()->build();
    $this->assertContains('user', $build['#cache']['contexts']);
    // The buttons carry a CSRF token, which belongs to the session.
    $this->assertContains('session', $build['#cache']['contexts']);
    $this->assertContains('manager_project_seen:' . $manager->id(), $build['#cache']['tags']);
    $this->assertContains('log_list', $build['#cache']['tags']);
  }

}
