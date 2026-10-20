<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;

/**
 * Tests the edit context pane.
 *
 * @group manager
 */
class ManagerContextPaneEditTest extends MailerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'views',
    'logbook',
    'manager',
  ];

  /**
   * The button explains what promoting and demoting the project does.
   */
  public function testDescribesPromoteAndDemote(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $project->setPromoted(FALSE)->save();
    $build = $this->pane()->build($project)['content'];
    $this->assertSame('Promote', (string) $build['button']['#value']);
    $this->assertStringContainsString('This action will promote the project.', (string) $build['description']['#markup']);
    $this->assertStringContainsString('<ul>', (string) $build['description']['#markup']);

    $project->setPromoted(TRUE)->save();
    $build = $this->pane()->build($project)['content'];
    $this->assertSame('Demote', (string) $build['button']['#value']);
    $this->assertStringContainsString('This action will demote the project.', (string) $build['description']['#markup']);
  }

  /**
   * An archived project has neither button nor description.
   */
  public function testArchivedHasNoContent(): void {
    $project = $this->createMailableProject(ProjectState::Open);
    $this->assertInstanceOf(Project::class, $project);
    $project->set('field_lifecycle', ProjectState::Declined->value)->save();
    $this->assertSame([], $this->pane()->build($project)['content']);
  }

  /**
   * Gets the edit pane.
   */
  protected function pane(): object {
    return $this->container->get('plugin.manager.manager_context_panes')->createInstance('edit');
  }

}
