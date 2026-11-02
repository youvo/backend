<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the edit pane offers the actions the user may post.
 *
 * The pane script reads the contract of each button: data-action names the
 * resource it posts to, data-pane-view the editor it opens instead.
 */
#[Group('manager')]
class ManagerContextPaneEditTest extends ManagerExistingSiteTestBase {

  /**
   * Gets the offered actions of the edit pane and how the script runs them.
   *
   * @return array<string, string>
   *   The contract of each action, keyed by the action: 'action:<resource>'
   *   for a button that posts, 'view:<view>' for one that opens an editor.
   */
  protected function actions(ProjectInterface $project): array {
    $pane = $this->container->get('plugin.manager.manager_context_panes')->createInstance('edit');
    $actions = [];
    foreach ($pane->build($project)['content']['actions'] ?? [] as $action => $item) {
      $attributes = $item['button']['#attributes'];
      $actions[$action] = isset($attributes['data-action']) ? 'action:' . $attributes['data-action'] : 'view:' . $attributes['data-pane-view'];
    }
    // The cheap check of the project list agrees with the built pane.
    $this->assertSame($actions !== [], $pane->hasActions($project));
    return $actions;
  }

  /**
   * A supervisor gets the actions the resources allow in each state.
   */
  public function testSupervisorActionsPerState(): void {

    $this->actAs($this->createSupervisor());

    $this->assertSame([
      'promote' => 'action:promote',
      'notify' => 'action:notify',
      'reset' => 'action:reset',
    ], $this->actions($this->createProject(ProjectState::Draft)));

    $this->assertSame([
      'promote' => 'action:promote',
      'hide' => 'action:hide',
      'reset' => 'action:reset',
      'archive' => 'view:archive',
    ], $this->actions($this->createProject(ProjectState::Open)));

    $hidden = $this->createProject(ProjectState::Completed);
    $hidden->setHidden()->save();
    $this->assertSame(['show', 'reset', 'archive'], array_keys($this->actions($hidden)));
    $this->assertSame('action:show', $this->actions($hidden)['show']);

    $promoted = $this->createProject(ProjectState::Ongoing);
    $promoted->setPromoted(TRUE)->save();
    $this->assertSame(['demote', 'hide', 'reset', 'archive'], array_keys($this->actions($promoted)));
    $this->assertSame('action:demote', $this->actions($promoted)['demote']);

    $this->assertSame(['reset' => 'action:reset'], $this->actions($this->createProject(ProjectState::Declined)));

    $archived = $this->createProject(ProjectState::Open);
    $archived->setArchived()->save();
    $this->assertSame([], $this->actions($archived));
  }

  /**
   * A manager only notifies drafts of their organizations.
   */
  public function testManagerActions(): void {

    $draft = $this->createProject(ProjectState::Draft);
    $this->actAs($this->managerOf($draft));
    $this->assertSame(['notify' => 'action:notify'], $this->actions($draft));

    $open = $this->createProject(ProjectState::Open);
    $this->actAs($this->managerOf($open));
    $this->assertSame([], $this->actions($open));
  }

}
