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
    foreach ([
      'creative_applied' => [
        'title' => $this->t('Creatives: Applied to a project'),
        'field' => 'field_applicants',
      ],
      'creative_participated' => [
        'title' => $this->t('Creatives: Took part in a project'),
        'field' => 'field_participants',
      ],
    ] as $key => $info) {
      $data['users_field_data'][$key] = [
        'title' => $info['title'],
        'help' => $this->t('Whether a project lists the creative in the field %field.', ['%field' => $info['field']]),
        'real field' => 'uid',
        'filter' => [
          'id' => 'creative_project_activity',
          'real field' => 'uid',
          'project_field' => $info['field'],
        ],
      ];
    }
    $data['users_field_data']['creative_has_link'] = [
      'title' => $this->t('Creatives: Has a portfolio or website'),
      'help' => $this->t('Whether the creative entered a website or a portfolio link.'),
      'real field' => 'uid',
      'filter' => ['id' => 'creative_has_link', 'real field' => 'uid'],
    ];
    if (isset($data['user__field_city']['field_city_value'])) {
      $data['user__field_city']['creatives_city'] = [
        'title' => $this->t('Creatives: City'),
        'help' => $this->t('The city of the creative. A creative without a city never contains the text.'),
        'filter' => ['id' => 'creatives_city', 'real field' => 'field_city_value'],
      ];
    }
  }

}
