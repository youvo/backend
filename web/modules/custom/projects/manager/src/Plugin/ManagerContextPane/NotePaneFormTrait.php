<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\manager\Form\ProjectNoteForm;
use Drupal\projects\ProjectInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds and processes a note form inside a context pane.
 *
 * The note forms read the pane arguments from a request attribute and check
 * them against the cached build info of a POST (see
 * NoteFormAuthorizationTrait); the pane compares the build info of the form it
 * actually got as well.
 */
trait NotePaneFormTrait {

  /**
   * Builds the form (and processes it on a POST).
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param class-string $form_class
   *   The form class.
   * @param \Drupal\projects\ProjectInterface $project
   *   The project of the pane.
   * @param \Drupal\correspondence\CorrespondenceInterface|null $entry
   *   The note, NULL for the add form.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The pane request.
   * @param string $done_flag
   *   The form state flag of a successful action.
   * @param string $failed_flag
   *   The form state flag of a failed action.
   * @param string $denied_flag
   *   The form state flag of a refused action.
   *
   * @return array{0: string, 1: array}
   *   The outcome ('denied', 'already', 'done', 'failed' or 'form') and the
   *   form render array.
   */
  protected function processNoteForm(FormBuilderInterface $form_builder, string $form_class, ProjectInterface $project, ?CorrespondenceInterface $entry, Request $request, string $done_flag, string $failed_flag, string $denied_flag): array {
    $form_state = new FormState();
    // Without this the form API would try to produce a redirect response.
    $form_state->disableRedirect();
    $form_state->addBuildInfo('args', [$project, $entry]);
    $expected = [(string) $project->id(), (string) $entry?->id()];
    // A POST with a form_build_id restores the build info of an earlier
    // build: tell the form which project and note this request is for.
    $request->attributes->set(ProjectNoteForm::PANE_ATTRIBUTE, $expected);
    $form = $form_builder->buildForm($form_class, $form_state);
    $args = $form_state->getBuildInfo()['args'] ?? [];
    $built = [
      ($args[0] ?? NULL) instanceof ProjectInterface ? (string) $args[0]->id() : '',
      ($args[1] ?? NULL) instanceof CorrespondenceInterface ? (string) $args[1]->id() : '',
    ];
    if ($built !== $expected || $form_state->get($denied_flag)) {
      return ['denied', []];
    }
    if ($form_state->get('already_submitted')) {
      return ['already', []];
    }
    if ($form_state->get($done_flag)) {
      return ['done', []];
    }
    return [$form_state->get($failed_flag) ? 'failed' : 'form', $form];
  }

}
