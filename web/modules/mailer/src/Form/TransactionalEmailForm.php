<?php

namespace Drupal\mailer\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\youvo\SimpleToken;
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
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);
    $form['#title'] = $this->t('Edit %label',
      ['%label' => $this->entity->label()]);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
      '#access' => $this->currentUser()->hasPermission('administer transactional emails'),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\mailer\Entity\TransactionalEmail::load',
      ],
      '#access' => $this->currentUser()->hasPermission('administer transactional emails'),
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->subject(),
      '#required' => TRUE,
    ];

    // Description for body field describing required and optional tokens.
    $tokens_description = [];
    // Ajax callback adds the adds_more button to tokens. Filter it out here.
    $tokens = array_filter($this->entity->tokens(TRUE), fn($t) => is_array($t));
    if ($required_tokens = array_filter($tokens, fn($t) => $t['required'] ?? FALSE)) {
      $tokens_description[] = $this->t('Required Tokens: @tokens', [
        '@tokens' => implode(', ', array_column($required_tokens, 'token')),
      ]);
    }
    if ($optional_tokens = array_filter($tokens, fn($t) => !isset($t['required']) || !$t['required'])) {
      $tokens_description[] = $this->t('Optional Tokens: @tokens', [
        '@tokens' => implode(', ', array_column($optional_tokens, 'token')),
      ]);
    }

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#default_value' => $this->entity->body(),
      '#description' => implode(' &mdash; ', $tokens_description),
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
      '#access' => $this->currentUser()->hasPermission('administer transactional emails'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Validates whether all required tokens are contained in the body.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    /** @var array $tokens_value */
    $tokens_value = $form_state->getValue('tokens');
    $tokens = SimpleToken::createMultiple($tokens_value);
    /** @var string $body */
    $body = $form_state->getValue('body');
    foreach ($tokens as $token) {
      if ($token->isRequired() && !$token->isContainedIn($body)) {
        $form_state->setErrorByName('body', $this->t('The body does not contain all required tokens.'));
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Remove empty values from tokens.
    /** @var array $token_values */
    $token_values = $form_state->getValue('tokens');
    $form_state->setValue('tokens', array_filter($token_values, fn($t) => !empty($t['token'])));
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function save(array $form, FormStateInterface $form_state): int {
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
