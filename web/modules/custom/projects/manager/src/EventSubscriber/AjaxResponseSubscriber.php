<?php

namespace Drupal\manager\EventSubscriber;

use Drupal\views\Ajax\ViewAjaxResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle AJAX responses.
 */
class AjaxResponseSubscriber implements EventSubscriberInterface {

  /**
   * Alter the views AJAX response commands.
   *
   * @param array $commands
   *   An array of commands to alter.
   */
  protected function removeScrollTopCommands(array &$commands): void {
    foreach ($commands as $delta => &$command) {
      if ($command['command'] === 'scrollTop') {
        unset($commands[$delta]);
      }
    }
  }

  /**
   * Renders the ajax commands right before preparing the result.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event, which contains the possible AjaxResponse object.
   */
  public function onResponse(ResponseEvent $event): void {

    $response = $event->getResponse();
    if (!$response instanceof ViewAjaxResponse) {
      return;
    }

    // Keep scroll top behavior when changing page.
    if (!empty($event->getRequest()->get('page'))) {
      return;
    }

    // Only act on the project manager view.
    $view = $response->getView();
    if ($view->id() !== 'project_manager') {
      return;
    }

    $commands = &$response->getCommands();
    $this->removeScrollTopCommands($commands);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [KernelEvents::RESPONSE => ['onResponse']];
  }

}
