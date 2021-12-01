<?php

namespace Drupal\youvo\EventSubscriber;

use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\youvo\BlockerModeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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
   * @param \Drupal\youvo\BlockerModeInterface $blocker_mode
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
        $this->forbiddenResponse($event);
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
    $this->forbiddenResponse($event);
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
