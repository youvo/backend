<?php

namespace Drupal\Tests\projects\ExistingSite;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\projects\Form\ProjectCompleteForm;
use Drupal\projects\Form\ProjectMediateForm;
use Drupal\projects\Form\ProjectPublishForm;
use Drupal\projects\Form\ProjectResetForm;
use Drupal\projects\Form\ProjectSubmitForm;
use Drupal\projects\Form\ProjectUnarchiveForm;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the project admin forms that dispatch a project event.
 */
#[Group('projects')]
class ProjectAdminFormRedirectTest extends ProjectResourceTestBase {

  /**
   * Submits an admin form for a project.
   */
  protected function submit(string $form_class, ProjectInterface $project, array $selected = []): FormStateInterface {
    $form_object = $this->container->get('class_resolver')->getInstanceFromDefinition($form_class);
    $form_state = (new FormState())->setValues([
      'project' => $project,
      'select_participants' => $selected,
    ]);
    $form = [];
    $form_object->submitForm($form, $form_state);
    return $form_state;
  }

  /**
   * Tests that the admin forms redirect to the project edit form.
   *
   * The redirect does not depend on whether the event succeeds.
   */
  public function testTransitionFormRedirect(): void {

    $project = $this->createProject();
    $forms = [
      ProjectSubmitForm::class,
      ProjectPublishForm::class,
      ProjectMediateForm::class,
      ProjectCompleteForm::class,
      ProjectResetForm::class,
      ProjectUnarchiveForm::class,
    ];
    foreach ($forms as $form_class) {
      $redirect = $this->submit($form_class, $project)->getRedirect();
      $this->assertSame('entity.project.edit_form', $redirect->getRouteName(), $form_class);
      $this->assertSame(['project' => $project->id()], $redirect->getRouteParameters(), $form_class);
    }
    // Errors are shown as messages, not thrown.
    $this->container->get('messenger')->deleteAll();
  }

  /**
   * Tests that the mediate form mediates the selected applicants only.
   */
  public function testMediateFormSelectsApplicants(): void {

    $project = $this->createProject(ProjectState::Open);
    $selected = $this->createCreative();
    $other = $this->createCreative();
    $project->appendApplicant($selected)->appendApplicant($other)->save();

    $this->submit(ProjectMediateForm::class, $project, [$selected->id() => $selected->id(), $other->id() => 0]);

    $stored = $this->reloadProject($project);
    $this->assertTrue($stored->lifecycle()->isOngoing());
    $this->assertTrue($stored->isParticipant($selected));
    $this->assertFalse($stored->isParticipant($other));
    $this->container->get('messenger')->deleteAll();
  }

}
