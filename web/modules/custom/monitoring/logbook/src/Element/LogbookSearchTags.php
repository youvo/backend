<?php

namespace Drupal\logbook\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Url;
use Drupal\logbook\SearchTags;
use Drupal\tagify\Element\EntityAutocompleteTagify;

/**
 * The search bar of the logbook: a Tagify input of mixed tags.
 *
 * It keeps the Tagify element's widget, but is fed by the logbook
 * autocomplete, which mixes people, organizations, projects and the typed
 * text. The value is the JSON list of tags that Tagify reads, built from the
 * tokens of SearchTags.
 *
 * Usage example:
 * @code
 * $form['tags'] = [
 *   '#type' => 'logbook_search_tags',
 *   '#default_value' => ['user:12', 'text:Feedback'],
 * ];
 * @endcode
 */
#[FormElement('logbook_search_tags')]
class LogbookSearchTags extends EntityAutocompleteTagify {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    // The parent requires a target type; the logbook route ignores it.
    $info['#target_type'] = 'user';
    $info['#max_items'] = SearchTags::MAX_TOKENS;
    $info['#suggestions_dropdown'] = 1;
    $info['#process'][] = [static::class, 'processSearchTags'];
    return $info;
  }

  /**
   * Points the widget at the logbook autocomplete.
   */
  public static function processSearchTags(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element['#attributes']['data-autocomplete-url'] = Url::fromRoute('logbook.search_autocomplete')->toString();
    $element['#attributes']['class'][] = 'logbook-search';
    // The script gives the Tagify input this name for screen readers.
    $element['#attributes']['aria-label'] ??= 'Logbuch durchsuchen: Person, Organisation, Projekt oder Text';
    $element['#attached']['drupalSettings']['tagify']['information_message']['no_matching_suggestions'] = 'Keine Vorschläge für:';
    $element['#attached']['library'][] = 'logbook/search';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state): string|false|null {
    // Without tags, submitted input still gives an empty value: NULL would let
    // the form builder show the raw input in the bar.
    $empty = $input === FALSE ? NULL : '';
    $tokens = SearchTags::fromInput($input === FALSE ? ($element['#default_value'] ?? []) : $input);
    if ($tokens === []) {
      return $empty;
    }

    $parsed = SearchTags::parse($tokens);
    $entity_type_manager = \Drupal::entityTypeManager();
    $entities = [
      'user' => $entity_type_manager->getStorage('user')->loadMultiple($parsed['user']),
      'project' => $entity_type_manager->getStorage('project')->loadMultiple($parsed['project']),
    ];
    $tags = [];
    foreach ($tokens as $token) {
      [$kind, $value] = explode(':', $token, 2);
      if ($kind === 'text') {
        $tags[] = SearchTags::textTag($value);
      }
      elseif (isset($entities[$kind][$value])) {
        $tags[] = SearchTags::tag($entities[$kind][$value]);
      }
    }
    // Ids of deleted entities are dropped, as invalid tokens are.
    return $tags ? json_encode($tags) : $empty;
  }

}
