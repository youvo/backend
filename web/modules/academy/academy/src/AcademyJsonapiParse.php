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
   */
  protected function resolveRelationshipData($links, $key) {
    if (empty($links['data'])) {
      return [];
    }
    return parent::resolveRelationshipData($links, $key);
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveAttributes($item) {

    // Filter empty states from checkboxes submission.
    if (isset($item['type']) && $item['type'] == 'checkboxes') {
      if (isset($item['attributes']['submission'])) {
        $item['attributes']['submission'] = array_filter(
          $item['attributes']['submission'],
          fn($s) => $s !== NULL && $s !== ""
        );
      }
    }
    return parent::resolveAttributes($item);
  }

}
