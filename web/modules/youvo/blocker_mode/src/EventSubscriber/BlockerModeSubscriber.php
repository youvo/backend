<?php

namespace Drupal\blocker_mode\EventSubscriber;

use Drupal\blocker_mode\BlockerModeInterface;
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
 * Maintenance mode subscriber for controller requests.
 */
class BlockerModeSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The maintenance mode.
   *
   * @var \Drupal\Core\Site\MaintenanceModeInterface
   */
  protected $blockerMode;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The bare HTML page renderer.
   *
   * @var \Drupal\Core\Render\BareHtmlPageRendererInterface
   */
  protected $bareHtmlPageRenderer;

  /**
   * Constructs a new MaintenanceModeSubscriber.
   *
   * @param \Drupal\blocker_mode\BlockerModeInterface $blocker_mode
   *   The blocker mode.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Render\BareHtmlPageRendererInterface $bare_html_page_renderer
   *   The bare HTML page renderer.
   */
  public function __construct(BlockerModeInterface $blocker_mode, AccountInterface $account, BareHtmlPageRendererInterface $bare_html_page_renderer) {
    $this->blockerMode = $blocker_mode;
    $this->account = $account;
    $this->bareHtmlPageRenderer = $bare_html_page_renderer;
  }

  /**
   * Returns the site blocker page.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onKernelRequestBlocker(RequestEvent $event) {

    $request = $event->getRequest();
    $route_match = RouteMatch::createFromRequest($request);

    if ($this->blockerMode->applies($request, $this->account)) {
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
   * Returns the site blocker page for exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onKernelExceptionBlocker(RequestEvent $event) {
    /** @var \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event */
    $exception = $event->getThrowable();
    $path = $event->getRequest()->getPathInfo();
    if ((str_starts_with($path, '/api') || str_starts_with($path, '/oauth')) &&
      $exception instanceof HttpException) {
      $response = new Response(
        $exception->getMessage(),
        $exception->getStatusCode(),
        $exception->getHeaders()
      );
      $event->setResponse($response);
    }
    else {
      $this->forbiddenResponse($event);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequestBlocker', 31];
    $events[KernelEvents::EXCEPTION][] = ['onKernelExceptionBlocker', 1];
    return $events;
  }

  /**
   * Delivers the forbidden response.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  private function forbiddenResponse(RequestEvent $event) {
    $request = $event->getRequest();
    \Drupal::service('page_cache_kill_switch')->trigger();
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

}
