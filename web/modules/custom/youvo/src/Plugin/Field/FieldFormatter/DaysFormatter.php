<?php

namespace Drupal\youvo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shows a number as days, such as "1 day" or "2.5 days".
 *
 * Unlike the number formatters, it keeps the decimals that a value has and
 * adds none, and it leaves out values that are not positive.
 */
#[FieldFormatter(
  id: 'days',
  label: new TranslatableMarkup('Days'),
  field_types: ['decimal', 'integer'],
)]
class DaysFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $days = (float) $item->get('value')->getValue();
      if ($days <= 0) {
        continue;
      }
      $count = (string) $days;
      $elements[$delta] = [
        '#markup' => $count === '1' ? $this->t('1 day') : $this->t('@count days', ['@count' => $count]),
      ];
    }
    return $elements;
  }

}
