<?php

namespace Drupal\manager\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Views hook implementations for the creatives finder.
 */
class CreativesViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   *
   * Adds the city filter of the creatives finder to the city field table. The
   * preferences of a creative who never set them count as "No": their boolean
   * filters accept a missing value as false.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    foreach (['field_jobs', 'field_newsletter', 'field_public_profile'] as $field) {
      if (isset($data['user__' . $field][$field . '_value']['filter'])) {
        $data['user__' . $field][$field . '_value']['filter']['accept null'] = TRUE;
      }
    }
    if (isset($data['user__field_city']['field_city_value'])) {
      $data['user__field_city']['creatives_city'] = [
        'title' => $this->t('Creatives: City'),
        'help' => $this->t('The city of the creative. A creative without a city never contains the text.'),
        'filter' => ['id' => 'creatives_city', 'real field' => 'field_city_value'],
      ];
    }
  }

}
