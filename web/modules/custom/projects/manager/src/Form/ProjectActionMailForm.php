<?php

namespace Drupal\manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\projects\ProjectInterface;

/**
 * The mail editor of an action that mails, such as decline or archive.
 *
 * The pane script posts the reason and the edited mail to the action's REST
 * resource, so that the pane and the frontend share one path.
 */
abstract class ProjectActionMailForm extends ProjectMailForm {

  /**
   * The action of the REST resource, project:<action>.
   */
  protected const string ACTION = '';

  /**
   * Gets the label of the submit button.
   */
  abstract protected function submitLabel(): TranslatableMarkup;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ProjectInterface $project = NULL, string $subtype = ''): array {
    $form = parent::buildForm($form, $form_state, $project, $subtype);
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#value'] = $this->submitLabel();
      $form['#attributes']['data-rest-action'] = static::ACTION;
      $form['#attributes']['data-reason'] = substr($subtype, strlen(static::ACTION) + 1);
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Never mails without the action: only the resource acts.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
