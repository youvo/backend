<?php

namespace Drupal\manager\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\correspondence\CorrespondenceInterface;
use Drupal\manager\Ajax\ManagerRefreshCommand;

/**
 * Asks a manager to confirm the deletion of a note.
 */
class ProjectNoteDeleteForm extends FormBase {

  /**
   * The id of the wrapper that the ajax callback replaces.
   */
  protected const WRAPPER_ID = 'manager-note-delete-form-wrapper';

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'manager_project_note_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?CorrespondenceInterface $correspondence = NULL): array {
    $form['#prefix'] = '<div id="' . self::WRAPPER_ID . '">';
    $form['#suffix'] = '</div>';
    $form['status_messages'] = ['#type' => 'status_messages'];
    $form['question'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Do you really want to delete the note "@title"? This cannot be undone.', [
        '@title' => $correspondence?->label() ?? '',
      ]),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => self::WRAPPER_ID,
      ],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('<none>'),
      '#attributes' => ['class' => ['dialog-cancel', 'button']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->get('deleted')) {
      return;
    }
    $form_state->set('delete_failed', FALSE);
    $correspondence = $this->entry($form_state);
    $deleted = FALSE;
    if ($correspondence->isNote() && $correspondence->access('delete', $this->currentUser())) {
      try {
        $correspondence->delete();
        $deleted = TRUE;
      }
      catch (EntityStorageException $exception) {
        $this->getLogger('manager')->error('A note could not be deleted: @message', ['@message' => $exception->getMessage()]);
      }
    }
    if (!$deleted) {
      $form_state->set('delete_failed', TRUE);
      $form_state->setRebuild();
      $this->messenger()->addError($this->t('The note could not be deleted.'));
      return;
    }
    $form_state->set('deleted', TRUE);
    // The ajax response closes the dialog and refreshes the pane instead.
    $request = $this->getRequest();
    if (!$request->query->has('ajax_form') && !$request->request->has('_drupal_ajax')) {
      $this->messenger()->addStatus($this->t('The note was deleted.'));
    }
  }

  /**
   * Ajax callback: closes the dialog on success, shows errors otherwise.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    if ($form_state->get('delete_failed') || $form_state->hasAnyErrors()) {
      return $response->addCommand(new ReplaceCommand('#' . self::WRAPPER_ID, $form));
    }
    return $response
      ->addCommand(new CloseModalDialogCommand())
      ->addCommand(new ManagerRefreshCommand($this->entry($form_state)->get('project')->target_id));
  }

  /**
   * Gets the entry from the build arguments.
   */
  protected function entry(FormStateInterface $form_state): CorrespondenceInterface {
    return $form_state->getBuildInfo()['args'][0];
  }

}
