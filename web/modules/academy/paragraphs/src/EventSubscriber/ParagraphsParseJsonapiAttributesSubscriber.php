<?php

namespace Drupal\paragraphs\EventSubscriber;

use Drupal\youvo\Event\ParseJsonapiAttributesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for the parsing of attributes in the json api response.
 *
 * @see \Drupal\youvo\AlterJsonapiParse
 */
class ParagraphsParseJsonapiAttributesSubscriber implements EventSubscriberInterface {

  /**
   * Resolve attributes in json api parsing.
   *
   * @param \Drupal\youvo\Event\ParseJsonapiAttributesEvent $event
   *   The event to process.
   */
  public function resolveAttributes(ParseJsonapiAttributesEvent $event) {

    $item = $event->getItem();

    // Rearrange values from multi-field for stats paragraphs. Merge stats and
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

    $event->setItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ParseJsonapiAttributesEvent::EVENT_NAME => 'resolveAttributes',
    ];
  }

}
