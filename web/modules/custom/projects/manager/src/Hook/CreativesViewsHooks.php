<?php

namespace Drupal\manager\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Views hook implementations for the creatives finder.
 */
class CreativesViewsHooks {

  use StringTranslationTrait;

  public function __construct(
    protected RequestStack $requestStack,
  ) {}

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
    $data['views']['creatives_mode_toggle'] = [
      'title' => $this->t('Creatives: Mode toggle'),
      'help' => $this->t('Links the table and the profiles mode of the creatives finder.'),
      'area' => ['id' => 'creatives_mode_toggle'],
    ];
  }

  /**
   * Implements hook_views_pre_view().
   *
   * With ?mode=profiles, the page of the creatives finder shows the cards of
   * the profiles display: it takes over its style, row and pager. The filters,
   * the sorts and the exposed form stay those of the page. The hook runs
   * before the view builds, so the style and pager plugins are not created
   * yet and take the overridden options.
   */
  #[Hook('views_pre_view')]
  public function viewsPreView(ViewExecutable $view, string $display_id, array &$args): void {
    if ($view->id() !== 'creatives' || $display_id !== 'page' || !$this->isProfilesMode()) {
      return;
    }
    $profiles = $view->displayHandlers->get('profiles');
    if ($profiles === NULL) {
      return;
    }
    foreach (['style', 'row', 'pager'] as $option) {
      $view->display_handler->overrideOption($option, $profiles->getOption($option));
    }
  }

  /**
   * Implements hook_views_pre_render().
   */
  #[Hook('views_pre_render')]
  public function viewsPreRender(ViewExecutable $view): void {
    if ($view->id() === 'creatives') {
      $view->element['#cache']['contexts'][] = 'url.query_args:mode';
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for views_exposed_form.
   *
   * Filtering in the profiles mode of the creatives finder stays in it.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {
    $view = $form_state->get('view');
    if (!$view instanceof ViewExecutable || $view->id() !== 'creatives' || $view->current_display !== 'page') {
      return;
    }
    if ($this->isProfilesMode()) {
      $form['mode'] = ['#type' => 'hidden', '#value' => 'profiles'];
    }
  }

  /**
   * Whether the current request asks for the profiles mode.
   *
   * Only "profiles" is a mode; any other value means the table.
   */
  protected function isProfilesMode(): bool {
    return $this->requestStack->getCurrentRequest()?->query->get('mode') === 'profiles';
  }

}
