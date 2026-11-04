<?php

namespace Drupal\youvo\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Shows a phone number typed as text as a link to call it.
 *
 * Core's telephone link formatter only works for telephone fields.
 */
#[FieldFormatter(
  id: 'string_telephone',
  label: new TranslatableMarkup('Telephone link'),
  field_types: ['string'],
)]
class StringTelephoneFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $phone = trim((string) $item->get('value')->getValue());
      $number = preg_replace('/[^\d+]/', '', $phone);
      if ($number === '') {
        continue;
      }
      $elements[$delta] = [
        '#type' => 'link',
        '#title' => $phone,
        '#url' => Url::fromUri('tel:' . $number),
      ];
    }
    return $elements;
  }

}
