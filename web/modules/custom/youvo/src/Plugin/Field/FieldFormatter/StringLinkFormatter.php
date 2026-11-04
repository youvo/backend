<?php

namespace Drupal\youvo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Shows an address typed as text as a link, labeled without the scheme.
 *
 * Only http(s) values become links, opening in a new tab; other values show
 * as text, so they do not look clickable. A value without an address, such
 * as "https://www.", is left out.
 */
#[FieldFormatter(
  id: 'string_link',
  label: new TranslatableMarkup('Link if http(s)'),
  field_types: ['string'],
)]
class StringLinkFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $value = trim((string) $item->get('value')->getValue());
      $label = rtrim(preg_replace('{^(https?://)?(www\.)?}i', '', $value), '/');
      if ($label === '') {
        continue;
      }
      $elements[$delta] = preg_match('{^https?://}i', $value)
        ? [
          '#type' => 'link',
          '#title' => $label,
          '#url' => Url::fromUri($value),
          '#attributes' => ['target' => '_blank', 'rel' => 'noopener'],
        ]
        : [
          '#type' => 'html_tag',
          '#tag' => 'span',
          'label' => ['#plain_text' => $label],
        ];
    }
    return $elements;
  }

}
