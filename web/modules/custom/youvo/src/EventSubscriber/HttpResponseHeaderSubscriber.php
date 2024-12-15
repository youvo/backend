<?php

namespace Drupal\youvo\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to alter the HTTP response header.
 */
class HttpResponseHeaderSubscriber implements EventSubscriberInterface {

  /**
   * Alters headers on all responses, also for subrequests.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onAllResponses(ResponseEvent $event): void {
    $response = $event->getResponse();
    $response->headers->set('X-Robots-Tag', 'noindex,nofollow');
    $response->headers->remove('X-Generator');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['onAllResponses', -1000];
    return $events;
  }

}
