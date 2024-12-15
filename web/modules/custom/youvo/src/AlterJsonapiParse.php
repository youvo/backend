<?php

namespace Drupal\youvo;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\jsonapi_include\JsonapiParse;
use Drupal\youvo\Event\ParseJsonapiAttributesEvent;
use Drupal\youvo\Event\ParseJsonapiRelationshipsEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Parses and alters JSON:API response.
 *
 * See jsonapi_include module.
 */
class AlterJsonapiParse extends JsonapiParse {

  /**
   * Constructs a new AlterJsonapiParse object.
   */
  public function __construct(
    RequestStack $request_stack,
    protected EventDispatcherInterface $eventDispatcher,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    parent::__construct($request_stack);
  }

  /**
   * {@inheritdoc}
   */
  protected function parseJsonContent(array $content): array {
    $content = parent::parseJsonContent($content);

    // Resolve offsets when pagination is requested.
    if (isset($content['links']['next']) || isset($content['links']['prev'])) {
      foreach ($content['links'] as $key => $link) {
        $offsets[$key] = $this->getOffset($link);
      }
      $content['offsets'] = $offsets ?? [];
    }

    // Unset links and jsonapi information in response.
    unset($content['links'], $content['jsonapi']);

    // Unset the display name here, because in some cases we don't want to leak
    // the user email or name.
    // See https://www.drupal.org/project/drupal/issues/3257608
    unset($content['data']['display_name']);

    // Unset meta data.
    unset($content['meta']);

    return $content;
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveRelationships($resource, $parent_key): array {

    // Nothing to do, if there are no relationships.
    if (empty($resource['relationships'])) {
      return $resource;
    }

    // Get keys for later and call parent.
    $keys = array_keys($resource['relationships']);
    $resource = parent::resolveRelationships($resource, $parent_key);

    // Rewrite alt to description for image files.
    if (($resource['type'] === 'file') && !empty($resource['meta']) && isset($resource['meta']['alt'])) {
      $resource['meta']['description'] = $resource['meta']['alt'];
      unset($resource['meta']['alt']);
    }

    // Allow other modules to alter the response.
    $event = new ParseJsonapiRelationshipsEvent($resource, $keys, $parent_key);
    $event = $this->eventDispatcher->dispatch($event);

    return $event->getResource();
  }

  /**
   * Resolves relationship data.
   *
   * Overwrite this method to provide an empty array, when the data is empty.
   * This ensures a consistent data type for includes.
   *
   * @todo Check this because we are changing the parents return type.
   *
   * @param array|mixed $links
   *   The data for resolve.
   * @param string $key
   *   Relationship key.
   *
   * @return array|null
   *   Result.
   */
  protected function resolveRelationshipData($links, $key): ?array {
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
   */
  protected function resolveAttributes($item): array {

    // Unset links from items.
    unset($item['links']['self']);
    if (empty($item['links'])) {
      unset($item['links']);
    }

    // Unset rel from file links and resolve href.
    if ($item['type'] === 'file') {
      if (!empty($item['links'])) {
        foreach ($item['links'] as &$link) {
          unset($link['meta']['rel']);
        }
        unset($link);
      }
      $item['href'] = $this->fileUrlGenerator
        ->generateAbsoluteString($item['attributes']['uri']['value']);
      unset($item['attributes']['uri']);
    }

    // Unset the display name here, because in some cases we don't want to leak
    // the user email or name.
    // See https://www.drupal.org/project/drupal/issues/3257608.
    unset($item['attributes']['display_name']);

    // Allow other modules to alter the item.
    $event = new ParseJsonapiAttributesEvent($item);
    $event = $this->eventDispatcher->dispatch($event);

    return parent::resolveAttributes($event->getItem());
  }

  /**
   * Gets offset from URL in jsonapi links property.
   */
  protected function getOffset(array $link): ?string {
    $url_parsed = UrlHelper::parse($link['href']);
    return $url_parsed['query']['page']['offset'] ?? NULL;
  }

}
