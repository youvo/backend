<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\projects\Entity\Project;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds and processes a manager mail form inside a context pane.
 *
 * The mail forms read the pane arguments from a request attribute and check
 * them against the cached build info of a POST (see
 * ProjectMailForm::authorizedProject()); the pane compares the build info of
 * the form it actually got as well.
 */
trait MailPaneFormTrait {

  /**
   * Builds the form (and processes it on a POST).
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param class-string $form_class
   *   The form class.
   * @param \Drupal\projects\Entity\Project $project
   *   The project of the pane.
   * @param string $subtype
   *   The mail subtype.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The pane request.
   *
   * @return array{0: string, 1: array, 2: \Drupal\Core\Form\FormStateInterface}
   *   The outcome ('denied', 'already', 'sent', 'failed' or 'form'), the form
   *   render array and the form state.
   */
  protected function processMailForm(FormBuilderInterface $form_builder, MessengerInterface $messenger, string $form_class, Project $project, string $subtype, Request $request): array {
    $form_state = new FormState();
    // Without this the form API would try to produce a redirect response.
    $form_state->disableRedirect();
    $form_state->addBuildInfo('args', [$project, $subtype]);
    // A POST with a form_build_id restores the build info of the earlier GET:
    // tell the form which project and subtype this request is for.
    $request->attributes->set('manager_pane_mail', [(string) $project->id(), $subtype]);
    $form = $form_builder->buildForm($form_class, $form_state);
    $args = $form_state->getBuildInfo()['args'] ?? [];
    $built_for = ($args[0] ?? NULL) instanceof Project ? [(string) $args[0]->id(), $args[1] ?? NULL] : NULL;
    if (($built_for !== NULL && $built_for !== [(string) $project->id(), $subtype]) || $form_state->get('send_denied')) {
      return ['denied', [], $form_state];
    }
    if ($form_state->get('already_submitted')) {
      return ['already', [], $form_state];
    }
    if ($form_state->get('sent')) {
      return ['sent', [], $form_state];
    }
    if ($form_state->get('send_failed')) {
      // symfony_mailer reports the transport error (host, port) on the
      // messenger as error or warning: show the manager only our own message.
      $messenger->deleteByType(MessengerInterface::TYPE_ERROR);
      $messenger->deleteByType(MessengerInterface::TYPE_WARNING);
      return ['failed', $form, $form_state];
    }
    return ['form', $form, $form_state];
  }

}
