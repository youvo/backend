<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\lifecycle\WorkflowPermissions;
use Drupal\manager\Plugin\ManagerContextPane\ManagerContextPaneEdit;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectState;
use Drupal\projects\Service\ProjectLifecycle;
use Drupal\rest\Entity\RestResourceConfig;
use Drupal\rest\RestResourceConfigInterface;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the edit pane offers the actions the user may post.
 */
#[CoversClass(ManagerContextPaneEdit::class)]
#[Group('manager')]
class ManagerContextPaneEditTest extends MailerKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'views',
    'logbook',
    'manager',
    'rest',
    'serialization',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The REST permissions exist with the resource configs.
    foreach (['promote', 'demote', 'hide', 'show', 'notify', 'reset', 'archive'] as $action) {
      RestResourceConfig::create([
        'id' => 'project.' . $action,
        'plugin_id' => 'project:' . $action,
        'granularity' => RestResourceConfigInterface::RESOURCE_GRANULARITY,
        'configuration' => ['methods' => ['POST'], 'formats' => ['json'], 'authentication' => ['cookie']],
      ])->save();
    }
    // User 1 bypasses all permission checks: use it up.
    $this->createMailableCreative('Root');
  }

  /**
   * Logs in a supervisor.
   */
  protected function actAsSupervisor(): void {
    $permissions = [
      'administer projects',
      'archive projects',
      'general manager access',
      'send project mails',
      WorkflowPermissions::bypassTransition(ProjectLifecycle::WORKFLOW_ID),
    ];
    foreach (['promote', 'demote', 'hide', 'show', 'notify', 'reset', 'archive'] as $action) {
      $permissions[] = 'restful post project:' . $action;
    }
    $supervisor = $this->accountWith($this->createMailableCreative('Supervisor ' . $this->randomMachineName()), $permissions);
    $this->container->get('current_user')->setAccount($supervisor);
  }

  /**
   * Logs in the manager of the project's organization.
   */
  protected function actAsManagerOf(Project $project): UserInterface {
    $manager = $this->accountWith($project->getOwner()->getManager(), [
      'general manager access',
      'send project mails',
      'restful post project:notify',
    ]);
    $this->container->get('current_user')->setAccount($manager);
    return $manager;
  }

  /**
   * Creates a saved project.
   */
  protected function project(ProjectState $state): Project {
    $project = $this->createMailableProject($state);
    $this->assertInstanceOf(Project::class, $project);
    return $project;
  }

  /**
   * Gets the offered actions of the edit pane.
   *
   * @return string[]
   *   The actions.
   */
  protected function actions(Project $project): array {
    $build = $this->container->get('plugin.manager.manager_context_panes')->createInstance('edit')->build($project);
    return array_keys($build['content']['actions'] ?? []);
  }

  /**
   * A supervisor sees the actions the resources allow in each state.
   */
  public function testSupervisorActionsPerState(): void {
    $this->actAsSupervisor();

    $this->assertSame(['promote', 'notify', 'reset'], $this->actions($this->project(ProjectState::Draft)));
    $this->assertSame(['promote', 'hide', 'reset', 'archive'], $this->actions($this->project(ProjectState::Open)));

    $hidden = $this->project(ProjectState::Completed);
    $hidden->setHidden()->save();
    $this->assertSame(['show', 'reset', 'archive'], $this->actions($hidden));

    $promoted = $this->project(ProjectState::Ongoing);
    $promoted->setPromoted(TRUE)->save();
    $this->assertSame(['demote', 'hide', 'reset', 'archive'], $this->actions($promoted));

    $this->assertSame(['reset'], $this->actions($this->project(ProjectState::Declined)));

    $archived = $this->project(ProjectState::Open);
    $archived->setArchived()->save();
    $this->assertSame([], $this->actions($archived));
  }

  /**
   * A manager only notifies drafts of their organizations.
   */
  public function testManagerActions(): void {
    $draft = $this->project(ProjectState::Draft);
    $this->actAsManagerOf($draft);
    $this->assertSame(['notify'], $this->actions($draft));

    $open = $this->project(ProjectState::Open);
    $this->actAsManagerOf($open);
    $this->assertSame([], $this->actions($open));
  }

  /**
   * The buttons post their action and explain what happens.
   */
  public function testButtonsAndDescriptions(): void {
    $this->actAsSupervisor();
    $project = $this->project(ProjectState::Open);
    $actions = $this->container->get('plugin.manager.manager_context_panes')->createInstance('edit')->build($project)['content']['actions'];

    $this->assertSame('Promote', (string) $actions['promote']['button']['#value']);
    $this->assertSame('promote', $actions['promote']['button']['#attributes']['data-action']);
    $this->assertContains('js-action-btn', $actions['promote']['button']['#attributes']['class']);
    $this->assertSame('item_list', $actions['hide']['description']['#theme']);
    $this->assertSame('archive', $actions['archive']['button']['#attributes']['data-pane-view']);
  }

}
