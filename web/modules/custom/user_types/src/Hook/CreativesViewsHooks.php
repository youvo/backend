<?php

namespace Drupal\user_types\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
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
   * Adds the filters of the creatives finder. Their boolean filters offer
   * "Yes" and "No". The preferences of a creative who
   * never set them count as "No": their filters accept a missing value as
   * false.
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    foreach (['field_jobs', 'field_newsletter', 'field_public_profile'] as $field) {
      if (isset($data['user__' . $field][$field . '_value']['filter'])) {
        $data['user__' . $field][$field . '_value']['filter']['accept null'] = TRUE;
        $data['user__' . $field][$field . '_value']['filter']['type'] = 'yes-no';
      }
    }
    // The projects build on the user types, so their filters are optional.
    if ($this->entityTypeManager->hasDefinition('project')) {
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
            'type' => 'yes-no',
          ],
        ];
      }
    }
    $data['users_field_data']['creative_has_link'] = [
      'title' => $this->t('Creatives: Has a portfolio or website'),
      'help' => $this->t('Whether the creative entered a website or a portfolio link.'),
      'real field' => 'uid',
      'filter' => ['id' => 'creative_has_link', 'real field' => 'uid', 'type' => 'yes-no'],
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
   * Keeps the main filters, the buttons and the switch between the table and
   * the profiles in one row and moves the other filters into two groups,
   * which open when one of their filters is set. The operators get short
   * labels, the skills become checkboxes with the skill icons.
   * Filtering and resetting in the profiles mode stays in it.
   */
  #[Hook('form_views_exposed_form_alter')]
  public function formViewsExposedFormAlter(array &$form, FormStateInterface $form_state): void {

    $view = $form_state->get('view');
    if (!$view instanceof ViewExecutable || $view->id() !== 'creatives' || $view->current_display !== 'page') {
      return;
    }

    // A date filter is a switch between "after" and "before", in the style of
    // the yes/no filters, and a date picker. The picker submits YYYY-MM-DD.
    foreach (['registered', 'last_access'] as $date) {
      if (isset($form[$date . '_wrapper'][$date . '_op']['#options'])) {
        $operator = &$form[$date . '_wrapper'][$date . '_op'];
        $operator['#type'] = 'radios';
        $operator['#options'] = ['>=' => $this->t('after'), '<' => $this->t('before')];
        $operator['#default_value'] = '>=';
        $operator['#title'] = $this->t('Compare');
        $operator['#title_display'] = 'invisible';
        $operator['#attributes']['class'][] = 'creatives-segmented';
        unset($operator['#size'], $operator);
      }
      if (isset($form[$date . '_wrapper'][$date]['value'])) {
        $value = &$form[$date . '_wrapper'][$date]['value'];
        $value['#type'] = 'date';
        unset($value['#placeholder'], $value['#size'], $value['#maxlength'], $value);
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

    // The yes/no filters are segmented controls: every state shows at a glance
    // and takes one click.
    $segmented = [
      'participated',
      'applied',
      'returned',
      'accessed',
      'has_avatar',
      'has_about',
      'has_link',
      'jobs',
      'newsletter',
      'public_profile',
    ];
    foreach ($segmented as $key) {
      if (isset($form[$key]['#options']['All'])) {
        $form[$key]['#type'] = 'radios';
        $form[$key]['#options']['All'] = $this->t('Any');
        $form[$key]['#default_value'] = 'All';
        $form[$key]['#attributes']['class'][] = 'creatives-segmented';
        unset($form[$key]['#size']);
      }
    }

    $input = $view->getExposedInput();
    // Each group lays out its filters in rows that belong together.
    $groups = [
      'activity' => [
        'title' => $this->t('Activity'),
        'rows' => [
          'dates' => [
            'registered' => 'registered_wrapper',
            'last_access' => 'last_access_wrapper',
          ],
          'states' => [
            'participated' => 'participated',
            'applied' => 'applied',
            'returned' => 'returned',
            'accessed' => 'accessed',
          ],
        ],
      ],
      'profile' => [
        'title' => $this->t('Profile'),
        'rows' => [
          'states' => [
            'has_avatar' => 'has_avatar',
            'has_about' => 'has_about',
            'has_link' => 'has_link',
            'jobs' => 'jobs',
            'newsletter' => 'newsletter',
            'public_profile' => 'public_profile',
          ],
          'tags' => [
            'tags' => 'tags',
          ],
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
      $active = 0;
      foreach ($info['rows'] as $row => $filters) {
        $form['groups'][$group][$row] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['creatives-filter-row']],
        ];
        foreach ($filters as $identifier => $key) {
          if (!isset($form[$key])) {
            continue;
          }
          $form['groups'][$group][$row][$key] = $form[$key];
          unset($form[$key]);
          if (static::isActive($input[$identifier] ?? NULL)) {
            $active++;
          }
        }
      }
      // A group with active filters opens and counts them in its summary, so
      // they show even when it is closed again.
      if ($active) {
        $form['groups'][$group]['#open'] = TRUE;
        $form['groups'][$group]['#title'] = $this->t('@group <span class="creatives-filter-count">@count<span class="visually-hidden"> active</span></span>', [
          '@group' => $info['title'],
          '@count' => $active,
        ]);
        $form['groups'][$group]['#attributes']['class'][] = 'creatives-filter-group--active';
      }
    }

    // Filled-in fields take the accent color, so the filters in use stand out.
    foreach (['name', 'mail', 'city'] as $key) {
      if (isset($form[$key]) && static::isActive($input[$key] ?? NULL)) {
        $form[$key]['#attributes']['class'][] = 'creatives-filter-active';
      }
    }
    // Without a date, the switch between "after" and "before" filters nothing
    // and stays quiet.
    foreach (['registered', 'last_access'] as $date) {
      $wrapper = &$form['groups']['activity']['dates'][$date . '_wrapper'];
      if (static::isActive($input[$date]['value'] ?? NULL)) {
        $wrapper[$date]['value']['#attributes']['class'][] = 'creatives-filter-active';
      }
      elseif (isset($wrapper[$date . '_op'])) {
        $wrapper[$date . '_op']['#attributes']['class'][] = 'creatives-segmented-quiet';
      }
      unset($wrapper);
    }

    // Apply is the primary action, Reset the secondary one next to it.
    if (isset($form['actions']['submit'])) {
      $form['actions']['submit']['#attributes']['class'][] = 'button--primary';
    }
    if (isset($form['actions']['reset'])) {
      $form['actions']['reset']['#attributes']['class'][] = 'creatives-reset';
    }

    if ($this->isProfilesMode()) {
      $form['mode'] = ['#type' => 'hidden', '#value' => 'profiles'];
      $form['#submit'][] = [static::class, 'resetToProfiles'];
    }

    // The switch between the table and the profiles ends the row of the main
    // filters. Its links keep the filters and the sort, and drop the page,
    // because the modes show a different number of creatives per page.
    $query = $this->requestStack->getCurrentRequest()?->query->all() ?? [];
    unset($query['page'], $query['mode']);
    $profiles = $this->isProfilesMode();
    $links = [];
    foreach ([
      'table' => [$this->t('Table'), $query, !$profiles],
      'profiles' => [$this->t('Profiles'), ['mode' => 'profiles'] + $query, $profiles],
    ] as $key => [$title, $link_query, $active]) {
      $links[$key] = [
        'title' => $title,
        'url' => Url::fromRoute('<current>', [], ['query' => $link_query]),
        'attributes' => $active ? ['aria-current' => 'page', 'class' => ['is-active']] : [],
      ];
    }
    $form['mode_toggle'] = [
      '#theme' => 'links',
      '#links' => $links,
      '#attributes' => ['class' => ['creatives-mode-toggle']],
      '#weight' => 55,
      '#cache' => ['contexts' => ['url.query_args']],
    ];
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
