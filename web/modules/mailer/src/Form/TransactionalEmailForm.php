<?php

namespace Drupal\mailer\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multivalue_form_element\Element\MultiValue;

/**
 * Transactional Email form.
 *
 * @property \Drupal\mailer\TransactionalEmailInterface $entity
 */
class TransactionalEmailForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the transactional email.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\mailer\Entity\TransactionalEmail::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => $this->entity->get('body'),
      '#description' => $this->t('Body of the transactional email.'),
    ];

    $form['tokens'] = [
      '#type' => 'multivalue',
      '#cardinality' => Multivalue::CARDINALITY_UNLIMITED,
      '#title' => $this->t('Tokens'),
      'token' => [
        '#type' => 'textfield',
        '#title' => $this->t('Token'),
        '#title_display' => 'invisible',
        '#maxlength' => 255,
      ],
      '#default_value' => $this->entity->get('tokens'),
      '#access' => $this->entity->isNew(),
      '#description' => $this->t('Tokens for the transactional email.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new transactional email %label.', $message_args)
      : $this->t('Updated transactional email %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
