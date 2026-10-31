<?php

namespace Drupal\feedback\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'feedback_options' formatter.
 *
 * The feedback stores the answers of radios and checkboxes as plain integers.
 * The values are the option indexes posted by the frontend. This formatter
 * maps them to labels configured in the formatter settings.
 *
 * @FieldFormatter(
 *   id = "feedback_options",
 *   label = @Translation("Feedback options"),
 *   field_types = {
 *     "integer"
 *   }
 * )
 */
class FeedbackOptionsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['options' => []] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $options = $this->getOptions();
    $elements = [];
    foreach ($items as $delta => $item) {
      $value = (int) $item->get('value')->getValue();
      $elements[$delta] = [
        '#plain_text' => $options[$value] ?? (string) $value,
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $lines = [];
    foreach ($this->getOptions() as $value => $label) {
      $lines[] = $value . '|' . $label;
    }
    $elements['options'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Options'),
      '#description' => $this->t('One option per line in the format <em>value|label</em>. Values without a label are displayed as they are stored.'),
      '#default_value' => implode("\n", $lines),
      '#element_validate' => [[static::class, 'validateOptions']],
    ];
    return $elements;
  }

  /**
   * Converts the options textarea into the stored settings structure.
   */
  public static function validateOptions(array $element, FormStateInterface $form_state): void {
    $options = [];
    foreach (preg_split('/\R/', (string) $element['#value']) as $line) {
      if (trim($line) === '') {
        continue;
      }
      $parts = explode('|', $line, 2);
      if (count($parts) !== 2 || !preg_match('/^-?\d+$/', trim($parts[0]))) {
        $form_state->setError($element, t('Each option must have the format <em>value|label</em> with an integer value.'));
        return;
      }
      $options[] = [
        'value' => (int) trim($parts[0]),
        'label' => trim($parts[1]),
      ];
    }
    $form_state->setValueForElement($element, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $count = count($this->getOptions());
    return [
      $count > 0
        ? $this->formatPlural($count, '1 option', '@count options')
        : $this->t('No options, values are displayed as they are stored'),
    ];
  }

  /**
   * Gets the configured options keyed by value.
   *
   * @return string[]
   *   The option labels keyed by value.
   */
  protected function getOptions(): array {
    $options = [];
    foreach ($this->getSetting('options') as $option) {
      $options[(int) $option['value']] = (string) $option['label'];
    }
    return $options;
  }

}
