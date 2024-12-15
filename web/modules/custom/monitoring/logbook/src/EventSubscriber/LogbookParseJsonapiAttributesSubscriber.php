<?php

namespace Drupal\logbook\EventSubscriber;

use Drupal\youvo\Event\ParseJsonapiAttributesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for the parsing of attributes in the json api response.
 *
 * @see \Drupal\youvo\AlterJsonapiParse
 */
class LogbookParseJsonapiAttributesSubscriber implements EventSubscriberInterface {

  /**
   * Resolves attributes in json api parsing.
   *
   * @todo This event subscriber is there to manicure a bug. This should be
   *   resolved, when the following is resolved:
   *   https://www.drupal.org/project/jsonapi_cross_bundles/issues/3070430
   *
   * @param \Drupal\youvo\Event\ParseJsonapiAttributesEvent $event
   *   The event to process.
   */
  public function resolveAttributes(ParseJsonapiAttributesEvent $event): void {
    $item = $event->getItem();
    unset($item['relationships']['log_type']);
    $event->setItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ParseJsonapiAttributesEvent::class => 'resolveAttributes'];
  }

}
