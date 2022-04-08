<?php

namespace Drupal\academy;

use Drupal\jsonapi_include\JsonapiParse;

/**
 * Class JsonapiParse.
 *
 * @see jsonapi_include
 */
class AcademyJsonapiParse extends JsonapiParse {

  /**
   * {@inheritdoc}
   *
   * Overwrite this method to sort includes from different bundles, e.g.
   * associated includes from /api/questions/radios and /api/questions/textarea.
   * Otherwise, entries will only be sorted per resource.
   */
  protected function resolveRelationships($resource, $parent_key) {

    // Nothing to do, if there are no relationships.
    if (empty($resource['relationships'])) {
      return $resource;
    }

    // Get keys for later and call parent.
    $keys = array_keys($resource['relationships']);
    $resource = parent::resolveRelationships($resource, $parent_key);

    // Iterate all keys of relationships and see if they should be sorted.
    foreach ($keys as $key) {

      // Skip, if there is no weight entry.
      if (!isset($resource[$key][0]['weight'])) {
        continue;
      }

      // Skip, if this is an evaluation paragraph. These are pre-sorted.
      if ($parent_key == 'paragraphs' && $resource['type'] == 'evaluation' && $key == 'questions') {
        continue;
      }

      // Otherwise, sort the included resource by weight.
      usort($resource[$key], fn($a, $b) => $a['weight'] <=> $b['weight']);
    }

    return $resource;
  }

  /**
   * {@inheritdoc}
   *
   * Overwrite this method to provide an empty array, when the data is empty.
   * This ensures a consistent data type for includes.
   */
  protected function resolveRelationshipData($links, $key) {
    if (empty($links['data'])) {
      if (is_array($links['data'])) {
        return [];
      }
      return NULL;
    }
    return parent::resolveRelationshipData($links, $key);
  }

  /**
   * {@inheritdoc}
   *
   * Overwrite this method to pop empty values from submission arrays. These
   * empty values are added beforehand to deliver the caching information.
   *
   * Also, handle multivalue fields.
   *
   * @see SubmissionFieldItemList
   * @see ParagraphForm
   */
  protected function resolveAttributes($item) {

    // Filter empty states from checkboxes submission.
    if (isset($item['type']) && in_array($item['type'], ['checkboxes', 'task'])) {
      if (isset($item['attributes']['submission'])) {
        $item['attributes']['submission'] = array_filter(
          $item['attributes']['submission'],
          fn($s) => $s !== NULL && $s !== ""
        );
      }
    }

    // Rearrange values from multifield for stats paragraphs. Merge stats and
    // description to one array. We can assume that both are the same length.
    if (isset($item['type']) && $item['type'] == 'stats') {
      if (isset($item['attributes']['stats']) && isset($item['attributes']['description'])) {
        $stats = [];
        foreach ($item['attributes']['stats'] as $key => $stat) {
          $stats[] = [$stat, $item['attributes']['description'][$key]];
        }
        $item['attributes']['stats'] = $stats;
        unset($item['attributes']['description']);
      }
    }

    return parent::resolveAttributes($item);
  }

  /**
   * {@inheritdoc}
   */
  protected function parseJsonContent($response) {
    $json = parent::parseJsonContent($response);

    // Unset the display name here, because in some cases we don't want to leak
    // the user email or name.
    // @todo https://www.drupal.org/project/drupal/issues/3257608
    unset($json['data']['display_name']);

    return $json;
  }

}
