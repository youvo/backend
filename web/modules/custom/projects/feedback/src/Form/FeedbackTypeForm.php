<?php

namespace Drupal\feedback\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\feedback\Entity\FeedbackType;

/**
 * Form handler for feedback type forms.
 */
class FeedbackTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    $entity_type = $this->entity;
    if ($this->operation === 'add') {
      $form['#title'] = $this->t('Add feedback type');
    }
    else {
      $form['#title'] = $this->t(
        'Edit %label feedback type',
        ['%label' => $entity_type->label()]
      );
    }

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $entity_type->label(),
      '#description' => $this->t('The human-readable name of this feedback type.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity_type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => [FeedbackType::class, 'load'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this feedback type. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save feedback type');
    $actions['delete']['#value'] = $this->t('Delete feedback type');
    return $actions;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $entity_type = $this->entity;

    /** @var \Drupal\feedback\FeedbackInterface $entity_type */
    $entity_type->set('id', trim($entity_type->id()));
    $entity_type->set('label', trim($entity_type->label()));

    $status = $entity_type->save();

    $t_args = ['%name' => $entity_type->label()];
    if ($status === SAVED_UPDATED) {
      $message = $this->t('The feedback type %name has been updated.', $t_args);
    }
    elseif ($status === SAVED_NEW) {
      $message = $this->t('The feedback type %name has been added.', $t_args);
    }
    if (isset($message)) {
      $this->messenger()->addStatus($message);
    }

    $form_state->setRedirectUrl($entity_type->toUrl('collection'));

    return $status;
  }

}
