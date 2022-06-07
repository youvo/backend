<?php

namespace Drupal\projects\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for the parsing of relationships in the json api response.
 *
 * @see \Drupal\youvo\AlterJsonapiParse
 */
class ProjectParseJsonapiRelationshipsSubscriber implements EventSubscriberInterface {

  /**
   * Resolves relationships for results in JSON:API parsing.
   */
  public function resolveRelationships(Event $event) {

    /** @var \Drupal\youvo\Event\ParseJsonapiRelationshipsEvent $event */
    if ($event->getParentKey() == 'result') {
      $resource = $event->getResource();
      $results = [];
      if (isset($resource['hyperlinks'])) {
        foreach ($resource['hyperlinks'] as $link) {
          $results[] = ['type' => 'link'] + $link;
        }
      }
      if (isset($resource['files'])) {
        foreach ($resource['files'] as $file) {
          $results[] = ['weight' => $file['meta']['weight'] ?? 0] + $file;
        }
      }
      usort($results, fn ($a, $b) => $a['weight'] <=> $b['weight']);
      foreach ($results as &$result) {
        unset($result['weight']);
        unset($result['meta']['weight']);
        unset($result['meta']['display']);
      }
      $resource['items'] = $results;
      unset($resource['hyperlinks']);
      unset($resource['files']);
      $event->setResource($resource);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return ['Drupal\youvo\Event\ParseJsonapiRelationshipsEvent' => 'resolveRelationships'];
  }

}
