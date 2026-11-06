<?php

namespace Drupal\mailer_door\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Url;
use Drupal\mailer_door\Controller\DoorLinkAutocompleteController;
use Drupal\tagify\Element\EntityAutocompleteTagify;

/**
 * A field of the door link form: a Tagify input of one project or user.
 *
 * It is fed by the door link autocomplete of its kind, which opens with
 * suggestions before anything is typed. Each tag names its type in a chip.
 * The value is the ID of the chosen entity; the widget gets its tag.
 *
 * Properties:
 * - #kind: project, recipient or subject.
 *
 * Usage example:
 * @code
 * $form['project'] = [
 *   '#type' => 'door_link_tags',
 *   '#kind' => 'project',
 *   '#default_value' => '12',
 * ];
 * @endcode
 */
#[FormElement('door_link_tags')]
class DoorLinkTags extends EntityAutocompleteTagify {

  /**
   * Gets the element defaults.
   */
  public function getInfo(): array {
    $info = parent::getInfo();
    $info['#kind'] = 'project';
    // The parent requires a target type; the process sets the one of the kind.
    $info['#target_type'] = 'project';
    $info['#cardinality'] = 1;
    // The number of suggestions shown; the cardinality limits the tags.
    $info['#max_items'] = DoorLinkAutocompleteController::LIMIT;
    // Suggestions show on click, before anything is typed.
    $info['#suggestions_dropdown'] = 0;
    array_unshift($info['#process'], [static::class, 'processKind']);
    $info['#process'][] = [static::class, 'processDoorLinkTags'];
    array_unshift($info['#pre_render'], [static::class, 'preRenderTag']);
    return $info;
  }

  /**
   * Gets the entity type of a kind.
   */
  protected static function targetType(array $element): string {
    return ($element['#kind'] ?? 'project') === 'project' ? 'project' : 'user';
  }

  /**
   * Sets the target type and the identifier of the kind.
   */
  public static function processKind(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $element['#target_type'] = static::targetType($element);
    // Tagify copies the classes of the input; the identifier finds its widget.
    $element['#identifier'] = 'door-link-tags-' . $element['#kind'];
    $element['#attributes']['class'][] = $element['#identifier'];
    return $element;
  }

  /**
   * Points the widget at the door link autocomplete of its kind.
   */
  public static function processDoorLinkTags(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $url = Url::fromRoute('mailer_door.links_autocomplete', ['kind' => $element['#kind']])->toString();
    $element['#attributes']['data-autocomplete-url'] = $url;
    // The script adds the chosen project to this URL.
    $element['#attributes']['data-door-link-url'] = $url;
    $element['#attributes']['data-door-link-kind'] = $element['#kind'];
    $element['#attached']['drupalSettings']['tagify']['information_message'] = [
      'limit_tag' => 'Höchstens so viele Einträge:',
      'no_matching_suggestions' => 'Keine Vorschläge für:',
    ];
    $element['#attached']['library'][] = 'mailer_door/door_link_tags';
    return $element;
  }

  /**
   * Gets the ID of the chosen entity.
   *
   * @return string|null
   *   The ID, or NULL without input and default. Submitted input without a
   *   valid tag gives an empty string: NULL would make the raw input the value.
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state): ?string {

    if ($input === FALSE) {
      $default = $element['#default_value'] ?? NULL;
      return $default === NULL || $default === '' ? NULL : (string) $default;
    }

    $tags = is_string($input) ? json_decode($input, TRUE) : NULL;
    $raw = is_array($tags) && is_array($tags[0] ?? NULL) ? ($tags[0]['entity_id'] ?? '') : '';
    $id = is_scalar($raw) ? (string) $raw : '';
    if (!ctype_digit($id) || (int) $id <= 0) {
      return '';
    }
    $entity = \Drupal::entityTypeManager()->getStorage(static::targetType($element))->load($id);
    return $entity === NULL ? '' : $id;
  }

  /**
   * Renders the chosen ID as the tag Tagify reads.
   */
  public static function preRenderTag(array $element): array {
    $id = (string) ($element['#value'] ?? '');
    $entity = $id === '' ? NULL : \Drupal::entityTypeManager()->getStorage(static::targetType($element))->load($id);
    $element['#value'] = $entity === NULL ? '' : json_encode([DoorLinkAutocompleteController::tag($entity)]);
    return $element;
  }

}
