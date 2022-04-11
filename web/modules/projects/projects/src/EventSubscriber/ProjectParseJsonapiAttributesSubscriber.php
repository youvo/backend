<?php

namespace Drupal\projects\EventSubscriber;

use Drupal\youvo\Event\ParseJsonapiAttributesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for the parsing of attributes in the json api response.
 *
 * @see \Drupal\youvo\AlterJsonapiParse
 */
class ProjectParseJsonapiAttributesSubscriber implements EventSubscriberInterface {

  /**
   * Resolves attributes in json api parsing.
   *
   * Builds array for loose boolean values in user status.
   *
   * @param \Drupal\youvo\Event\ParseJsonapiAttributesEvent $event
   *   The event to process.
   */
  public function resolveAttributes(ParseJsonapiAttributesEvent $event) {

    $item = $event->getItem();

    if (isset($item['type']) && $item['type'] == 'project') {
      if (isset($item['attributes']['user_status'])) {
        $data = $item['attributes']['user_status'];
        $item['attributes']['user_status'] = [
          'applicant' => $data[0],
          'participant' => $data[1],
          'manager' => $data[2],
        ];
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
