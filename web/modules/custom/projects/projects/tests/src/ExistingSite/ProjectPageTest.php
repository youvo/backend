<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
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
   * Renders the full view of a project.
   */
  protected function renderProject(ProjectInterface $project): string {
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('project')
      ->view($project, 'full');
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
