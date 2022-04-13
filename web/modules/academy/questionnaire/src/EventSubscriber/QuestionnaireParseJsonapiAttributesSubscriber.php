<?php

namespace Drupal\questionnaire\EventSubscriber;

use Drupal\youvo\Event\ParseJsonapiAttributesEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for the parsing of attributes in the json api response.
 *
 * @see \Drupal\youvo\AlterJsonapiParse
 */
class QuestionnaireParseJsonapiAttributesSubscriber implements EventSubscriberInterface {

  /**
   * Resolve attributes in json api parsing.
   *
   * Hook this method to pop empty values from submission arrays. These
   * empty values are added beforehand to deliver the caching information.
   *
   * @see SubmissionFieldItemList
   * @see ParagraphForm
   *
   * @param \Drupal\youvo\Event\ParseJsonapiAttributesEvent $event
   *   The event to process.
   */
  public function resolveAttributes(ParseJsonapiAttributesEvent $event) {

    $item = $event->getItem();

    // Filter empty states from checkboxes submission.
    if (isset($item['type']) && in_array($item['type'], ['checkboxes', 'task'])) {
      if (isset($item['attributes']['submission'])) {
        $item['attributes']['submission'] = array_filter(
          $item['attributes']['submission'],
          fn($s) => $s !== NULL && $s !== ""
        );
      }
    }

    $event->setItem($item);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ParseJsonapiAttributesEvent::class => 'resolveAttributes',
    ];
  }

}
