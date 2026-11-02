<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\manager\Form\ProjectMailForm;
use Drupal\projects\Entity\Project;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds and processes a manager mail form inside a context pane.
 *
 * The mail forms read the pane arguments from a request attribute and check
 * them against the cached build info of a POST (see
 * ProjectMailForm::authorizedProject()); the pane compares the build info of
 * the form it actually got as well. The using pane provides $formBuilder and
 * $messenger.
 */
trait MailPaneFormTrait {

  /**
   * Builds the form (and processes it on a POST).
   *
   * @param class-string $form_class
   *   The form class.
   * @param \Drupal\projects\Entity\Project $project
   *   The project of the pane.
   * @param string $subtype
   *   The mail subtype.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The pane request.
   *
   * @return array{0: string, 1: array}
   *   The outcome ('denied', 'already', 'done', 'failed' or 'form') and the
   *   form render array.
   */
  protected function processMailForm(string $form_class, Project $project, string $subtype, Request $request): array {

    $form_state = new FormState();
    // Without this the form API would try to produce a redirect response.
    $form_state->disableRedirect();
    $form_state->addBuildInfo('args', [$project, $subtype]);
    $expected = [(string) $project->id(), $subtype];
    // A POST with a form_build_id restores the build info of the earlier GET:
    // tell the form which project and subtype this request is for.
    $request->attributes->set(ProjectMailForm::PANE_ATTRIBUTE, $expected);
    $form = $this->formBuilder->buildForm($form_class, $form_state);

    $args = $form_state->getBuildInfo()['args'] ?? [];
    $built = ($args[0] ?? NULL) instanceof Project ? [(string) $args[0]->id(), $args[1] ?? NULL] : NULL;
    if (($built !== NULL && $built !== $expected) || $form_state->get('send_denied')) {
      return ['denied', []];
    }
    if ($form_state->get('already_submitted')) {
      return ['already', []];
    }
    if ($form_state->get('sent')) {
      return ['done', []];
    }
    if ($form_state->get('send_failed')) {
      // symfony_mailer reports the transport error (host, port) on the
      // messenger as error or warning: show the manager only our own message.
      $this->messenger->deleteByType(MessengerInterface::TYPE_ERROR);
      $this->messenger->deleteByType(MessengerInterface::TYPE_WARNING);
      return ['failed', $form];
    }
    return ['form', $form];
  }

}
