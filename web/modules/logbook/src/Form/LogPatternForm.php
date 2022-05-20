<?php

namespace Drupal\logbook\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multivalue_form_element\Element\MultiValue;

/**
 * Log pattern form.
 *
 * @property \Drupal\logbook\LogPatternInterface $entity
 */
class LogPatternForm extends EntityForm {

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
      '#description' => $this->t('Label for the log pattern.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\logbook\Entity\LogPattern::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#default_value' => $this->entity->text(),
      '#description' => implode(' &mdash; ', []),
    ];

    $form['public_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Public text'),
      // '#title_display' => 'invisible',
      '#default_value' => $this->entity->publicText(),
      '#description' => implode(' &mdash; ', []),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    $form['promote'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Promoted'),
      '#default_value' => $this->entity->promoted(),
    ];

    $form['hidden'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hidden'),
      '#default_value' => $this->entity->hidden(),
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
      'required' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Required'),
      ],
      '#default_value' => $this->entity->tokens(TRUE),
      '#access' => $this->currentUser()->hasPermission('administer log patterns'),
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
      ? $this->t('Created new log pattern %label.', $message_args)
      : $this->t('Updated log pattern %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
