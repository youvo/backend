<?php

namespace Drupal\logbook\Form;

use Drupal\youvo\TranslationFormButtonsTrait;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\logbook\Entity\LogText;
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\youvo\SimpleToken;

/**
 * Log pattern form.
 *
 * @property \Drupal\logbook\LogPatternInterface $entity
 */
class LogPatternForm extends BundleEntityFormBase {

  use TranslationFormButtonsTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    if ($this->operation == 'add') {
      $form['#title'] = $this->t('Add log pattern');
    }
    else {
      $form['#title'] = $this->t(
        'Edit %label log pattern',
        ['%label' => $this->entity->label()]
      );

      /** @var \Drupal\logbook\Entity\LogText $log_text */
      $log_text = $this->entity->getLogTextEntity();

      if ($log_text->getEntityType()->hasLinkTemplate('drupal:content-translation-overview')) {
        static::addTranslationButtons($form, $log_text);
      }
    }

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
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => '\Drupal\logbook\Entity\LogPattern::load',
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name for this log pattern. It must only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#default_value' => $this->entity->text(),
      '#description' => implode(' &mdash; ', []),
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Public text'),
    ];

    $form['advanced']['public_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Public text'),
      '#title_display' => 'invisible',
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

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save log pattern');
    $actions['delete']['#value'] = $this->t('Delete log pattern');
    return $actions;
  }

  /**
   * {@inheritdoc}
   *
   * Validates whether all required tokens are contained in the texts.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    /** @var array $tokens_value */
    $tokens_value = $form_state->getValue('tokens');
    $tokens = SimpleToken::createMultiple($tokens_value);
    /** @var string $text */
    $text = $form_state->getValue('text');
    /** @var string $public_text */
    $public_text = $form_state->getValue('public_text');
    foreach ($tokens as $token) {
      if ($token->isRequired() && !$token->isContainedIn($text)) {
        $form_state->setErrorByName('text', $this->t('The text does not contain all required tokens.'));
      }
      if ($token->isRequired() && !$token->isContainedIn($public_text)) {
        $form_state->setErrorByName('public_text', $this->t('The public text does not contain all required tokens.'));
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
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);

    // Create text entity.
    if ($result == SAVED_NEW) {
      $log_text = LogText::create([
        'log_pattern' => $this->entity->id(),
        'text' => $form_state->getValue('text'),
        'public_text' => $form_state->getValue('public_text'),
      ]);
    }
    else {
      $log_text = $this->entity->getLogTextEntity();
      $log_text->setText($form_state->getValue('text'));
      $log_text->setPublicText($form_state->getValue('public_text'));
    }
    $log_text->save();

    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new log pattern %label.', $message_args)
      : $this->t('Updated log pattern %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
