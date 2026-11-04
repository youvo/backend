<?php

namespace Drupal\youvo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shows only the summary of a text, as plain text.
 *
 * Core's summary formatter falls back to the trimmed text, which would repeat
 * the beginning of a text that is shown next to it.
 */
#[FieldFormatter(
  id: 'text_summary_only',
  label: new TranslatableMarkup('Summary only'),
  field_types: ['text_with_summary'],
)]
class SummaryFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $summary = trim((string) $item->get('summary')->getValue());
      if ($summary !== '') {
        $elements[$delta] = ['#plain_text' => $summary];
      }
    }
    return $elements;
  }

}
