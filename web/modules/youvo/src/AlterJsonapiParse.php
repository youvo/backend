<?php

namespace Drupal\youvo;

use Drupal\Component\Utility\UrlHelper;
use Drupal\jsonapi_include\JsonapiParse;
use Drupal\youvo\Event\ParseJsonapiAttributesEvent;
use Drupal\youvo\Event\ParseJsonapiRelationshipsEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class to dispatch events to alter JsonapiParse.
 *
 * See jsonapi_include module.
 */
class AlterJsonapiParse extends JsonapiParse {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Constructs a AlterJsonapiParse object.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveRelationships($resource, $parent_key) {

    // Nothing to do, if there are no relationships.
    if (empty($resource['relationships'])) {
      return $resource;
    }

    // Get keys for later and call parent.
    $keys = array_keys($resource['relationships']);
    $resource = parent::resolveRelationships($resource, $parent_key);

    // Allow other modules to alter the response.
    $event = new ParseJsonapiRelationshipsEvent($resource, $keys, $parent_key);
    $event = $this->eventDispatcher->dispatch($event);

    return $event->getResource();
  }

  /**
   * Resolve data.
   *
   * Overwrite this method to provide an empty array, when the data is empty.
   * This ensures a consistent data type for includes.
   *
   * @param array|mixed $links
   *   The data for resolve.
   * @param string $key
   *   Relationship key.
   *
   * @return array|null
   *   Result.
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
   */
  protected function resolveAttributes($item) {

    // Unset links from items.
    unset($item['links']['self']);
    if (empty($item['links'])) {
      unset($item['links']);
    }

    // Unset the display name here, because in some cases we don't want to leak
    // the user email or name.
    // @todo https://www.drupal.org/project/drupal/issues/3257608
    unset($item['attributes']['display_name']);

    // Allow other modules to alter the item.
    $event = new ParseJsonapiAttributesEvent($item);
    $event = $this->eventDispatcher->dispatch($event);

    return parent::resolveAttributes($event->getItem());
  }

  /**
   * {@inheritdoc}
   */
  protected function parseJsonContent($response) {
    $json = parent::parseJsonContent($response);

    // Resolve offsets when pagination is requested.
    if (isset($json['links']['next']) || isset($json['links']['prev'])) {
      foreach ($json['links'] as $key => $link) {
        $json['offsets'][$key] = $this->getOffset($link);
      }
    }

    // Unset links and jsonapi information in response.
    unset($json['links']);
    unset($json['jsonapi']);

    // Unset the display name here, because in some cases we don't want to leak
    // the user email or name.
    // @todo https://www.drupal.org/project/drupal/issues/3257608
    unset($json['data']['display_name']);

    // Unset meta data.
    unset($json['meta']);

    return $json;
  }

  /**
   * Gets offset from URL in jsonapi links property.
   */
  protected function getOffset(array $link) {
    $url_parsed = UrlHelper::parse($link['href']);
    return $url_parsed['query']['page']['offset'] ?? NULL;
  }

}
