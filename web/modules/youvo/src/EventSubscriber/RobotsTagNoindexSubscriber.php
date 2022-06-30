<?php

namespace Drupal\youvo\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle finished responses.
 */
class RobotsTagNoindexSubscriber implements EventSubscriberInterface {

  /**
   * Sets extra headers on any responses, also subrequest ones.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onAllResponds(ResponseEvent $event) {
    $response = $event->getResponse();
    $response->headers->set('X-Robots-Tag', 'noindex');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onAllResponds', 1000];
    return $events;
  }

}
