<?php

namespace Drupal\Tests\manager\Kernel;

use Drupal\manager\Plugin\ManagerContextPane\ManagerContextPaneMail;
use Drupal\projects\Entity\Project;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Kernel\MailerKernelTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the mail context pane.
 *
 * @group manager
 */
class ManagerContextPaneMailTest extends MailerKernelTestBase {

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
   * The pane lists only the mails of the project's state, as in-pane links.
   */
  public function testListsMailsForState(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertInstanceOf(Project::class, $project);
    $this->container->get('current_user')->setAccount($project->getOwner()->getManager());
    $build = $this->pane()->build($project);
    $this->assertSame('mail', $build['#type']);
    $links = $build['content']['mails'];
    $this->assertCount(1, $links);
    $first = reset($links);
    $this->assertSame('mail', $first['#attributes']['data-pane-view']);
    $this->assertSame('subtype=reminder_draft', $first['#attributes']['data-pane-params']);
    $this->assertNotContains('use-ajax', $first['#attributes']['class']);
    $this->assertStringContainsString('/project/manage/context/' . $project->id(), $first['#url']->toString());
    $this->assertStringContainsString('view=mail', $first['#url']->toString());
    $this->assertArrayNotHasKey('last', $build['content']);
  }

  /**
   * Without access the pane has no content.
   */
  public function testNoAccessNoContent(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertInstanceOf(Project::class, $project);
    $this->container->get('current_user')->setAccount($this->createMailableCreative('Other'));
    $this->assertSame([], $this->pane()->build($project)['content'] ?? []);
  }

  /**
   * The latest sent manager mail is shown, failed and automatic ones are not.
   */
  public function testLastSentLine(): void {
    $project = $this->createMailableProject(ProjectState::Draft);
    $this->assertInstanceOf(Project::class, $project);
    $manager = $project->getOwner()->getManager();
    $role = Role::create(['id' => 'manager', 'label' => 'Manager']);
    $role->grantPermission('send project mails')->grantPermission('view correspondence')->save();
    $manager->addRole('manager')->save();
    $this->container->get('current_user')->setAccount($manager);
    $recorder = $this->container->get('mailer.correspondence_recorder');
    $recorder->recordMail($project, 'reminder_draft', ['a@example.com'], 'S', 'B', FALSE, 'manager', (int) $manager->id());
    $recorder->recordMail($project, 'reminder_draft', ['a@example.com'], 'S', 'B', TRUE, 'automatic');
    $this->assertArrayNotHasKey('last', $this->pane()->build($project)['content']);
    $recorder->recordMail($project, 'reminder_draft', ['a@example.com'], 'S', 'B', TRUE, 'manager', (int) $manager->id());
    $this->assertArrayHasKey('last', $this->pane()->build($project)['content']);
  }

  /**
   * The last sent line shows for the assigned manager of unpublished projects.
   */
  public function testLastSentLineOnUnpublishedProjects(): void {
    $this->createMailableCreative('Root');
    foreach ([ProjectState::Draft, ProjectState::Pending] as $state) {
      $project = $this->createMailableProject($state);
      $this->assertInstanceOf(Project::class, $project);
      $manager = $this->accountWith($project->getOwner()->getManager(), ['send project mails', 'view correspondence']);
      $this->container->get('current_user')->setAccount($manager);
      $this->container->get('mailer.correspondence_recorder')
        ->recordMail($project, 'reminder_draft', ['a@example.com'], 'S', 'B', TRUE, 'manager', (int) $manager->id());
      $this->assertArrayHasKey('last', $this->pane()->build($project)['content'], $state->name);
    }
  }

  /**
   * The empty text shows for an account with access only.
   */
  public function testEmptyTextOnlyWithAccess(): void {
    $this->createMailableCreative('Root');
    $project = $this->createMailableProject(ProjectState::Completed);
    $this->assertInstanceOf(Project::class, $project);
    $manager = $this->accountWith($project->getOwner()->getManager(), ['send project mails']);
    $this->container->get('current_user')->setAccount($manager);
    $build = $this->pane()->build($project);
    $this->assertArrayNotHasKey('mails', $build['content']);
    $this->assertArrayHasKey('empty', $build['content']);

    $this->container->get('current_user')->setAccount($this->createMailableCreative('Other'));
    $build = $this->pane()->build($project);
    $this->assertArrayNotHasKey('empty', $build['content']);
    $this->assertSame([], $build['content']);
  }

  /**
   * Gets the pane plugin.
   */
  protected function pane(): ManagerContextPaneMail {
    return $this->container->get('plugin.manager.manager_context_panes')->createInstance('mail');
  }

}
