<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\logbook\Entity\Log;
use Drupal\projects\Entity\ProjectComment;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the admin page of a project with the theme of the site.
 */
#[Group('projects')]
class ProjectPageTest extends ProjectResourceTestBase {

  /**
   * The page shows the project once, its applicants only to their viewers.
   */
  public function testApplicantsByViewer(): void {

    $project = $this->createProject(ProjectState::Open);
    $project->set('title', 'Plakate für das Stadtteilfest');
    $applicant = $this->createCreative();
    $applicant->set('field_name', 'Mara Völker')->save();
    $project->appendApplicant($applicant)->save();

    $current_user = $this->container->get('current_user');
    $current_user->setAccount($project->getOwner()->getManager());
    $html = $this->renderProject($project);
    $this->assertSame(1, substr_count($html, '<h1 class="project-page-title">Plakate für das Stadtteilfest</h1>'));
    $this->assertStringContainsString('Mara Völker', $html);

    // A manager of another organization does not see the applicants.
    $current_user->setAccount($this->createManager());
    $html = $this->renderProject($project);
    $this->assertStringContainsString('project-page-title', $html);
    $this->assertStringNotContainsString('Mara Völker', $html);
  }

  /**
   * An archived project shows its status and the reason from its log.
   */
  public function testArchiveReason(): void {

    $project = $this->createProject(ProjectState::Open);
    $this->container->get('current_user')->setAccount(User::load(1));
    $html = $this->renderProject($project);
    $this->assertStringContainsString('<dt>Status</dt><dd>Active</dd>', $html);
    $this->assertStringNotContainsString('Archive reason', $html);

    $project->setArchived()->save();
    $log = Log::create([
      'type' => 'project_archive',
      'author' => 1,
      'project' => $project->id(),
      'misc' => Json::encode(['reason' => ProjectArchiveReason::External->value]),
    ]);
    $log->save();
    $this->markEntityForCleanup($log);
    $html = $this->renderProject($project);
    $this->assertStringContainsString('<dt>Status</dt><dd>Archived</dd>', $html);
    $this->assertStringContainsString('<dt>Archive reason</dt><dd>' . ProjectArchiveReason::External->label() . '</dd>', $html);
  }

  /**
   * The result lists its links in order and the comments of all givers.
   */
  public function testResult(): void {

    $project = $this->createProject(ProjectState::Completed);
    $result = $project->getResult();
    $result->setLinks([
      ['value' => 'https://example.org/film', 'weight' => 1, 'description' => 'Imagefilm'],
      ['value' => 'https://example.org/site', 'weight' => 0, 'description' => ''],
    ]);
    $creative = $this->createCreative();
    $creative->set('field_name', 'Mara Völker')->save();
    foreach ([$creative, $project->getOwner()] as $author) {
      $comment = ProjectComment::create([
        'value' => 'Kommentar von ' . $author->id(),
        'project_result' => $result->id(),
        'author' => $author->id(),
      ]);
      $comment->save();
      $this->markEntityForCleanup($comment);
      $result->appendComment($comment);
    }
    $result->save();

    $this->container->get('current_user')->setAccount(User::load(1));
    $html = $this->renderProject($this->reloadProject($project));
    // Without a description, the address is the label.
    $site = strpos($html, '>example.org/site<');
    $film = strpos($html, '>Imagefilm<');
    $this->assertNotFalse($site);
    $this->assertNotFalse($film);
    $this->assertLessThan($film, $site);
    $this->assertStringContainsString('example.org/film</span>', $html);
    // Both links leave the website.
    $this->assertSame(2, substr_count($html, 'class="project-page-external"'));
    $this->assertStringContainsString('Kommentar von ' . $creative->id(), $html);
    $this->assertStringContainsString('Kommentar von ' . $project->getOwner()->id(), $html);
  }

  /**
   * Renders the full view of a project.
   */
  protected function renderProject(ProjectInterface $project): string {
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('project')
      ->view($project, 'full');
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
