<?php

namespace Drupal\projects\EventSubscriber;

use Drupal\Component\EventDispatcher\Event;
use Drupal\youvo\Event\ParseJsonapiRelationshipsEvent;
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
  public function resolveRelationships(Event $event): void {

    /** @var \Drupal\youvo\Event\ParseJsonapiRelationshipsEvent $event */
    if ($event->getParentKey() === 'result') {
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
      usort($results, static fn ($a, $b) => $a['weight'] <=> $b['weight']);
      foreach ($results as &$result) {
        unset($result['weight'], $result['meta']['weight'], $result['meta']['display']);
      }
      unset($result);
      $resource['items'] = $results;
      unset($resource['hyperlinks'], $resource['files']);
      $event->setResource($resource);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ParseJsonapiRelationshipsEvent::class => 'resolveRelationships'];
  }

}
