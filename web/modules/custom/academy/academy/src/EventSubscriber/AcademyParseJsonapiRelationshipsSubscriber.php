<?php

namespace Drupal\academy\EventSubscriber;

use Drupal\youvo\Event\ParseJsonapiRelationshipsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for the parsing of relationships in the json api response.
 *
 * @see \Drupal\youvo\AlterJsonapiParse
 */
class AcademyParseJsonapiRelationshipsSubscriber implements EventSubscriberInterface {

  /**
   * Resolve relationships in json api parsing.
   *
   * Overwrite this method to sort includes from different bundles, e.g.,
   * associated includes from /api/questions/radios and /api/questions/textarea.
   * Otherwise, entries will only be sorted per resource.
   *
   * @param \Drupal\youvo\Event\ParseJsonapiRelationshipsEvent $event
   *   The event to process.
   */
  public function resolveRelationships(ParseJsonapiRelationshipsEvent $event): void {

    $resource = $event->getResource();

    // Iterate all keys of relationships and see if they should be sorted.
    foreach ($event->getKeys() as $key) {

      // Skip, if there is no weight entry.
      if (!isset($resource[$key][0]['weight'])) {
        continue;
      }

      // Skip, if this is an evaluation paragraph. These are pre-sorted.
      if (
        isset($resource['type']) &&
        $resource['type'] === 'evaluation' &&
        $key === 'questions' &&
        $event->getParentKey() === 'paragraphs'
      ) {
        continue;
      }

      // Otherwise, sort the included resource by weight.
      usort($resource[$key], static fn($a, $b) => $a['weight'] <=> $b['weight']);
    }

    $event->setResource($resource);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ParseJsonapiRelationshipsEvent::class => 'resolveRelationships'];
  }

}
