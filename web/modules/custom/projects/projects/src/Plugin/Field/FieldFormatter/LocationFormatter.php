<?php

namespace Drupal\projects\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shows where a project takes place: its city when local, else remote.
 *
 * The city is a field of its own, so the formatter of the local flag reads it
 * with its access.
 */
#[FieldFormatter(
  id: 'project_location',
  label: new TranslatableMarkup('Location'),
  field_types: ['boolean'],
)]
class LocationFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['city_field' => 'field_city'] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form['city_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City field'),
      '#description' => $this->t('The machine name of the field with the city of a local project.'),
      '#default_value' => $this->getSetting('city_field'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [$this->t('City field: @field', ['@field' => $this->getSetting('city_field')])];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    $elements = [];
    $entity = $items->getEntity();
    $city_field = $this->getSetting('city_field');
    foreach ($items as $delta => $item) {
      if (!$item->get('value')->getValue()) {
        $elements[$delta] = ['#plain_text' => (string) $this->t('Remote')];
        continue;
      }
      $city = '';
      $cache = new CacheableMetadata();
      if ($entity->hasField($city_field)) {
        $access = $entity->get($city_field)->access('view', NULL, TRUE);
        $cache->addCacheableDependency($access);
        $city = $access->isAllowed() ? trim((string) $entity->get($city_field)->value) : '';
      }
      $elements[$delta] = ['#plain_text' => $city !== '' ? $city : (string) $this->t('On site')];
      $cache->applyTo($elements[$delta]);
    }
    return $elements;
  }

}
