<?php

namespace Drupal\manager\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
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
    protected EntityTypeManagerInterface $entityTypeManager,
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
   * Keeps the main filters, the sorts and the buttons in one row and moves the
   * other filters into three groups, which open when one of their filters is
   * set. The operators get short labels, the skills become checkboxes with
   * the skill icons.
   * Filtering and resetting in the profiles mode stays in it.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {

    $view = $form_state->get('view');
    if (!$view instanceof ViewExecutable || $view->id() !== 'creatives' || $view->current_display !== 'page') {
      return;
    }

    $labels = [
      'registered_wrapper' => ['registered_op' => ['>=' => $this->t('after'), '<' => $this->t('before')]],
      'last_access_wrapper' => ['last_access_op' => ['>=' => $this->t('after'), '<' => $this->t('before')]],
    ];
    foreach ($labels as $wrapper => $operators) {
      foreach ($operators as $operator => $options) {
        foreach ($options as $key => $label) {
          if (isset($form[$wrapper][$operator]['#options'][$key])) {
            $form[$wrapper][$operator]['#options'][$key] = $label;
          }
        }
      }
    }

    // The skills are checkboxes showing the skill icons, as on the profiles.
    if (isset($form['skills'])) {
      $form['skills']['#type'] = 'checkboxes';
      unset($form['skills']['#multiple'], $form['skills']['#size']);
      $form['skills']['#element_validate'][] = [static::class, 'validateSkills'];
      $form['skills']['#attributes']['class'][] = 'creatives-skills';
      $terms = $this->entityTypeManager->getStorage('taxonomy_term')
        ->loadMultiple(array_keys($form['skills']['#options']));
      $cache = CacheableMetadata::createFromRenderArray($form);
      foreach ($terms as $tid => $term) {
        $cache->addCacheableDependency($term);
        $label = Html::escape((string) $term->label());
        $form['skills']['#options'][$tid] = Markup::create(sprintf(
          '<span class="skill" data-skill="%s" data-size="small" title="%s" aria-hidden="true"></span><span class="visually-hidden">%s</span>',
          Html::escape((string) $term->get('machine_name')->value),
          $label,
          $label,
        ));
      }
      $cache->applyTo($form);
    }

    // The tags are a Tagify input, as the tags on the creative form. In this
    // GET form, Tagify submits the chosen tags as tags[]=<tid>.
    if (isset($form['tags']['#target_type'])) {
      $form['tags'] = [
        '#type' => 'entity_autocomplete_tagify',
        '#target_type' => 'taxonomy_term',
        '#tags' => TRUE,
        '#cardinality' => -1,
        '#max_items' => 20,
        '#selection_handler' => $form['tags']['#selection_handler'] ?? 'default',
        '#selection_settings' => $form['tags']['#selection_settings'] ?? [],
        '#placeholder' => $this->t('Search tags'),
        '#attributes' => ['class' => ['creatives-tags']],
        '#element_validate' => [[static::class, 'validateTags']],
      ];
    }

    // Views sets the labels of the filters at the top level of the form when
    // it renders it. The filters in a group take them along instead.
    foreach ($form['#info'] ?? [] as $id => $info) {
      foreach (['label' => '#title', 'description' => '#description'] as $source => $property) {
        if (!empty($info[$source]) && isset($form[$info['value']])) {
          $form[$info['value']][$property] = $info[$source];
          $form['#info'][$id][$source] = '';
        }
      }
    }

    $input = $view->getExposedInput();
    $groups = [
      'skills_and_tags' => [
        'title' => $this->t('Skills and tags'),
        'filters' => ['skills' => 'skills', 'tags' => 'tags'],
      ],
      'activity' => [
        'title' => $this->t('Activity'),
        'filters' => [
          'registered' => 'registered_wrapper',
          'last_access' => 'last_access_wrapper',
          'never_logged_in' => 'never_logged_in',
          'applied' => 'applied',
          'participated' => 'participated',
        ],
      ],
      'profile' => [
        'title' => $this->t('Profile'),
        'filters' => [
          'has_avatar' => 'has_avatar',
          'has_about' => 'has_about',
          'has_link' => 'has_link',
          'jobs' => 'jobs',
          'newsletter' => 'newsletter',
          'public_profile' => 'public_profile',
        ],
      ],
    ];
    // The buttons end the row of the main filters, the groups follow below.
    $form['actions']['#weight'] = 50;
    $form['groups'] = [
      '#type' => 'container',
      '#weight' => 60,
      '#attributes' => ['class' => ['creatives-filter-groups']],
    ];
    foreach ($groups as $group => $info) {
      $form['groups'][$group] = [
        '#type' => 'details',
        '#title' => $info['title'],
        '#open' => FALSE,
        '#attributes' => ['class' => ['creatives-filter-group']],
      ];
      foreach ($info['filters'] as $identifier => $key) {
        if (!isset($form[$key])) {
          continue;
        }
        $form['groups'][$group][$key] = $form[$key];
        unset($form[$key]);
        if (static::isActive($input[$identifier] ?? NULL)) {
          $form['groups'][$group]['#open'] = TRUE;
        }
      }
    }

    if ($this->isProfilesMode()) {
      $form['mode'] = ['#type' => 'hidden', '#value' => 'profiles'];
      $form['#submit'][] = [static::class, 'resetToProfiles'];
    }
  }

  /**
   * Keeps only the checked skills as the value of the skills checkboxes.
   *
   * Unchecked checkboxes submit 0, which the term filter would take as a term.
   */
  public static function validateSkills(array &$element, FormStateInterface $form_state): void {
    $value = array_filter((array) $element['#value']);
    $form_state->setValueForElement($element, array_combine($value, $value));
  }

  /**
   * Turns the Tagify value into the term references the tags filter expects.
   *
   * Tagify's value is a JSON list of the chosen tags with their entity IDs.
   */
  public static function validateTags(array &$element, FormStateInterface $form_state): void {
    $value = $form_state->getValue($element['#parents']);
    $items = is_string($value) && $value !== '' ? json_decode($value, TRUE) : NULL;
    $tags = [];
    foreach (is_array($items) ? $items : [] as $item) {
      if (isset($item['entity_id']) && is_numeric($item['entity_id'])) {
        $tags[] = ['target_id' => (int) $item['entity_id']];
      }
    }
    $form_state->setValueForElement($element, $tags ?: NULL);
  }

  /**
   * Returns from a reset in the profiles mode to the empty profiles mode.
   */
  public static function resetToProfiles(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    if (($trigger['#array_parents'] ?? []) === ['actions', 'reset']) {
      $form_state->setRedirect('<current>', [], ['query' => ['mode' => 'profiles']]);
    }
  }

  /**
   * Whether an exposed input value differs from the empty default.
   *
   * Empty strings, "All" and arrays of them, such as a date without a value,
   * are the default. "0" is a value.
   */
  protected static function isActive(mixed $value): bool {
    if (is_array($value)) {
      foreach ($value as $item) {
        if (static::isActive($item)) {
          return TRUE;
        }
      }
      return FALSE;
    }
    return $value !== NULL && $value !== '' && $value !== 'All';
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
