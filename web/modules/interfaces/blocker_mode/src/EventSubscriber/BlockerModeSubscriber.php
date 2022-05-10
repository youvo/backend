<?php

namespace Drupal\blocker_mode\EventSubscriber;

use Drupal\blocker_mode\BlockerModeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
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
 * Maintenance mode subscriber for controller requests.
 */
class BlockerModeSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The maintenance mode.
   *
   * @var \Drupal\blocker_mode\BlockerModeInterface
   */
  protected BlockerModeInterface $blockerMode;

  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $account;

  /**
   * The bare HTML page renderer.
   *
   * @var \Drupal\Core\Render\BareHtmlPageRendererInterface
   */
  protected BareHtmlPageRendererInterface $bareHtmlPageRenderer;

  /**
   * The page cache kill switch.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected KillSwitch $pageCacheKillSwitch;

  /**
   * The youvo settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a new MaintenanceModeSubscriber.
   *
   * @param \Drupal\blocker_mode\BlockerModeInterface $blocker_mode
   *   The blocker mode.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Render\BareHtmlPageRendererInterface $bare_html_page_renderer
   *   The bare HTML page renderer.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $page_cache_kill_switch
   *   The page cache kill switch.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    BlockerModeInterface $blocker_mode,
    AccountInterface $account,
    BareHtmlPageRendererInterface $bare_html_page_renderer,
    KillSwitch $page_cache_kill_switch,
    ConfigFactoryInterface $config_factory
  ) {
    $this->blockerMode = $blocker_mode;
    $this->account = $account;
    $this->bareHtmlPageRenderer = $bare_html_page_renderer;
    $this->pageCacheKillSwitch = $page_cache_kill_switch;
    $this->config = $config_factory->get('youvo.settings');
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
   * Returns the site blocker page for exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function onKernelExceptionBlocker(RequestEvent $event) {
    /** @var \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event */
    $exception = $event->getThrowable();
    $path = $event->getRequest()->getPathInfo();
    $prefix = $this->config->get('api_prefix');
    if (
      str_contains($path, $prefix . '/api') ||
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

}
