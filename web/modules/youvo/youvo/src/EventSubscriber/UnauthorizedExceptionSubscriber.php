<?php

namespace Drupal\youvo\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Maintenance mode subscriber for controller requests.
 */
class UnauthorizedExceptionSubscriber implements EventSubscriberInterface {

  /**
   * Try to catch unauthorized requests and forward the exception the caller in
   * the response. Triggers before logging @see getSubscribedEvents().
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onKernelUnauthorizedExceptionCatcher(RequestEvent $event) {
    /** @var \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event */
    $exception = $event->getThrowable();
    $path = $event->getRequest()->getPathInfo();
    if ($exception instanceof UnauthorizedHttpException) {
      $headers = $exception->getHeaders();
      if (array_key_exists('WWW-Authenticate', $headers) &&
        str_starts_with($headers['WWW-Authenticate'], 'Bearer realm="OAuth"')) {
        $response = new Response(
          $exception->getMessage(),
          $exception->getStatusCode(),
          $exception->getHeaders()
        );
        $event->setResponse($response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = ['onKernelUnauthorizedExceptionCatcher', 51];
    return $events;
  }

}
