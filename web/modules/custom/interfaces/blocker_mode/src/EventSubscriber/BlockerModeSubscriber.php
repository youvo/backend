<?php

namespace Drupal\blocker_mode\EventSubscriber;

use Drupal\blocker_mode\BlockerModeInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Blocker mode subscriber for controller requests.
 */
class BlockerModeSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Constructs a BlockerModeSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Render\BareHtmlPageRendererInterface $bareHtmlPageRenderer
   *   The bare HTML page renderer.
   * @param \Drupal\blocker_mode\BlockerModeInterface $blockerMode
   *   The blocker mode.
   * @param string $jsonApiBasePath
   *   The JSON:API base path.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $pageCacheKillSwitch
   *   The page cache kill switch.
   */
  public function __construct(
    protected AccountInterface $account,
    protected BareHtmlPageRendererInterface $bareHtmlPageRenderer,
    protected BlockerModeInterface $blockerMode,
    protected string $jsonApiBasePath,
    protected KillSwitch $pageCacheKillSwitch
  ) {}

  /**
   * Handles the kernel request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event to process.
   */
  public function onKernelRequestBlocker(RequestEvent $event) {

    $request = $event->getRequest();
    $route_match = RouteMatch::createFromRequest($request);

    if ($this->blockerMode->applies($request)) {
      if (!$this->blockerMode->exempt($route_match, $this->account)) {

        // One last effort to redirect the user. Can happen if logged-in user
        // tries to access /user/login which redirects to user canonical.
        if ($this->account->hasPermission('access site') &&
          RouteMatch::createFromRequest($event->getRequest())->getRouteName() == 'entity.user.canonical') {
          $redirect_url = Url::fromRoute('youvo.dashboard');
          $event->setResponse(new RedirectResponse($redirect_url->toString()));
        }
        // Access forbidden.
        else {
          $this->forbiddenResponse($event);
        }
      }
    }
  }

  /**
   * Handles the kernel exception.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event to process.
   */
  public function onKernelExceptionBlocker(RequestEvent $event) {
    /** @var \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event */
    $exception = $event->getThrowable();
    $path = $event->getRequest()->getPathInfo();
    if (
      str_contains($path, $this->jsonApiBasePath . '/') ||
      str_contains($path, '/oauth/token')
    ) {
      if ($exception instanceof HttpException) {
        $response = new Response(
          $exception->getMessage(),
          $exception->getStatusCode(),
          $exception->getHeaders()
        );
      }
      else {
        $response = new Response(
          $exception->getMessage(),
          $exception->getCode()
        );
      }
      $event->setResponse($response);
    }
    else {
      $this->forbiddenResponse($event);
    }
  }

  /**
   * Delivers the forbidden response.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  private function forbiddenResponse(RequestEvent $event) {
    $request = $event->getRequest();
    $this->pageCacheKillSwitch->trigger();
    if ($request->getRequestFormat() !== 'html') {
      $response = new Response('Forbidden', 403, ['Content-Type' => 'text/plain']);
      $event->setResponse($response);
      return;
    }
    drupal_maintenance_theme();
    $response = $this->bareHtmlPageRenderer->renderBarePage(['#markup' => $this->t('Forbidden')], $this->t('Data Provider'), 'maintenance_page');
    $response->setStatusCode(403);
    $event->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequestBlocker', 31];
    $events[KernelEvents::EXCEPTION][] = ['onKernelExceptionBlocker', 1];
    return $events;
  }

}
