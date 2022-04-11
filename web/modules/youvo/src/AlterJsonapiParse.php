<?php

namespace Drupal\youvo;

use Drupal\jsonapi_include\JsonapiParse;
use Drupal\youvo\Event\ParseJsonapiAttributesEvent;
use Drupal\youvo\Event\ParseJsonapiRelationshipsEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class to dispatch events to alter JsonapiParse.
 *
 * @see jsonapi_include
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
    $event = $this->eventDispatcher
      ->dispatch($event, ParseJsonapiRelationshipsEvent::EVENT_NAME);

    return $event->getResource();
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
   */
  protected function resolveAttributes($item) {

    // Allow other modules to alter the item.
    $event = new ParseJsonapiAttributesEvent($item);
    $event = $this->eventDispatcher
      ->dispatch($event, ParseJsonapiAttributesEvent::EVENT_NAME);

    return parent::resolveAttributes($event->getItem());
  }

}
