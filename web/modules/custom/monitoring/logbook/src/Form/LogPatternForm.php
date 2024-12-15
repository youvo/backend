<?php

namespace Drupal\logbook\Form;

use Drupal\Component\Utility\Color;
use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\logbook\Entity\LogText;
use Drupal\logbook\LogTextInterface;
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\youvo\SimpleToken;
use Drupal\youvo\TranslationFormButtonsTrait;

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
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    if ($this->operation === 'add') {
      $form['#title'] = $this->t('Add log pattern');
    }
    else {
      $form['#title'] = $this->t(
        'Edit %label log pattern',
        ['%label' => $this->entity->label()]
      );

      $log_text = $this->entity->getLogTextEntity();

      if (
        $log_text instanceof LogTextInterface &&
        $log_text->getEntityType()->hasLinkTemplate('drupal:content-translation-overview')
      ) {
        $this->addTranslationButtons($form, $log_text);
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

    // Description for text field describing required and optional tokens.
    $tokens_description = [];
    // Ajax callback adds the adds_more button to tokens. Filter it out here.
    $tokens = array_filter($this->entity->getTokens(TRUE), fn($t) => is_array($t));
    $allowed_public_tokens = [
      '%Project',
      '%Organization',
      '%Manager',
      '%Creatives',
      '%Creative',
    ];
    $public_tokens = array_filter($tokens, static fn($t) => in_array($t['token'], $allowed_public_tokens, TRUE));
    if ($required_tokens = array_filter($tokens, static fn($t) => $t['required'] ?? FALSE)) {
      $tokens_description[] = $this->t('Required Tokens: @tokens', [
        '@tokens' => implode(', ', array_column($required_tokens, 'token')),
      ]);

    }
    if ($required_public_tokens = array_filter($public_tokens, static fn($t) => $t['required'] ?? FALSE)) {
      $public_tokens_description[] = $this->t('Required Tokens: @tokens', [
        '@tokens' => implode(', ', array_column($required_public_tokens, 'token')),
      ]);
    }
    if ($optional_tokens = array_filter($tokens, static fn($t) => !isset($t['required']) || !$t['required'])) {
      $tokens_description[] = $this->t('Optional Tokens: @tokens', [
        '@tokens' => implode(', ', array_column($optional_tokens, 'token')),
      ]);
    }
    if ($optional_public_tokens = array_filter($public_tokens, static fn($t) => !isset($t['required']) || !$t['required'])) {
      $public_tokens_description[] = $this->t('Optional Tokens: @tokens', [
        '@tokens' => implode(', ', array_column($optional_public_tokens, 'token')),
      ]);
    }
    $public_tokens_description[] = $this->t('Optionally determines the text in the site-wide logbook. Uses text as defined above as fallback.');

    $form['text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Text'),
      '#default_value' => $this->entity->getText(),
      '#required' => TRUE,
      '#description' => implode(' &mdash; ', $tokens_description),
    ];

    $form['details_public_text'] = [
      '#type' => 'details',
      '#title' => $this->t('Public text'),
      '#open' => $this->entity->isNew(),
    ];

    $form['details_public_text']['public_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Public text'),
      '#title_display' => 'invisible',
      '#default_value' => $this->entity->getPublicText(),
      '#description' => implode(' &mdash; ', $public_tokens_description),
    ];

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => $this->entity->isNew(),
    ];

    $form['settings']['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Indicates whether this type of log should be tracking.'),
      '#disabled' => !$this->currentUser()->hasPermission('administer log pattern'),
      '#default_value' => $this->entity->isEnabled(),
      '#suffix' => '<hr>',
    ];

    $form['settings']['detectable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Detectable'),
      '#description' => $this->t('This log is accessable in the administration logbook.'),
      '#default_value' => $this->entity->isDetectable(),
    ];

    $form['settings']['observable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Observable'),
      '#description' => $this->t('This log is accessable for managers with respect to their organizations.'),
      '#default_value' => $this->entity->isObservable(),
    ];

    $form['settings']['public'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Public'),
      '#description' => $this->t('This log is accessable for authenticated users.'),
      '#default_value' => $this->entity->isPublic(),
      '#suffix' => '<hr>',
    ];

    $form['settings']['promote'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Promoted'),
      '#description' => $this->t('This log is promoted in the site-wide logbook.'),
      '#default_value' => $this->entity->isPromoted(),
    ];

    $form['settings']['hidden'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hidden'),
      '#description' => $this->t('This log is still accessable as above, but will be obscured.'),
      '#default_value' => $this->entity->isHidden(),
      '#suffix' => '<hr>',
    ];

    $form['settings']['color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background color hex code'),
      '#size' => 8,
      '#attributes' => ['placeholder' => '#FFFFFF'],
      '#maxlength' => 7,
      '#default_value' => $this->entity->getColor(),
    ];

    $form['details_tokens'] = [
      '#type' => 'details',
      '#title' => $this->t('Tokens'),
      '#open' => $this->entity->isNew(),
      '#access' => $this->currentUser()->hasPermission('administer log pattern'),
    ];

    $form['details_tokens']['tokens'] = [
      '#type' => 'multivalue',
      '#cardinality' => Multivalue::CARDINALITY_UNLIMITED,
      '#title' => $this->t('Tokens'),
      '#title_display' => 'invisible',
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
      '#default_value' => $this->entity->getTokens(TRUE),
      '#access' => $this->currentUser()->hasPermission('administer log pattern'),
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save log pattern');
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
      if (!empty($public_text) && $token->isRequired() && !$token->isContainedIn($public_text)) {
        $form_state->setErrorByName('public_text', $this->t('The public text does not contain all required tokens.'));
      }
    }
    $color = $form_state->getValue('color');
    if (!empty($color) && !Color::validateHex($form_state->getValue('color'))) {
      $form_state->setErrorByName('color', $this->t('Please enter a valid hex color code.'));
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
    $form_state->setValue('tokens', array_filter($token_values, static fn($t) => !empty($t['token'])));
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    // Create text entity.
    if ($result === SAVED_NEW) {
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
    $message = $result === SAVED_NEW
      ? $this->t('Created new log pattern %label.', $message_args)
      : $this->t('Updated log pattern %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
