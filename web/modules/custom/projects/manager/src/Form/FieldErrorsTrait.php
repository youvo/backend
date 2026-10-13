<?php

namespace Drupal\manager\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

/**
 * Shows the errors of a pane form right below the invalid fields.
 */
trait FieldErrorsTrait {

  /**
   * Shows the error of each invalid field right below it.
   *
   * The pane renders the form without the inline form errors module, so the
   * messages would only reach the status message banner far above the field.
   * This covers the core required errors as well as the ones set by the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string[] $fields
   *   The names of the fields that show their error.
   */
  protected function showFieldErrors(array &$form, FormStateInterface $form_state, array $fields): void {
    $done = [];
    foreach ($form_state->getErrors() as $name => $message) {
      $field = explode('][', (string) $name)[0];
      if (!in_array($field, $fields, TRUE) || isset($done[$field]) || !isset($form[$field])) {
        continue;
      }
      $done[$field] = TRUE;
      $id = 'manager-' . $form['#form_id'] . '-' . $field . '-error';
      $form[$field]['#suffix'] = '<div class="manager-pane-field-error" id="' . $id . '" role="alert">' . Html::escape((string) $message) . '</div>';
      if (isset($form[$field]['#type']) && in_array($form[$field]['#type'], ['textfield', 'textarea'], TRUE)) {
        $form[$field]['#attributes']['aria-describedby'] = $id;
      }
    }
  }

}
