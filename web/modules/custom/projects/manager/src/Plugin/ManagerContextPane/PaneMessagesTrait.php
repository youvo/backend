<?php

namespace Drupal\manager\Plugin\ManagerContextPane;

use Drupal\Core\Messenger\MessengerInterface;

/**
 * Renders the messenger messages of a context pane as compact notices.
 *
 * The Drupal status messages are a full width banner, too bulky inside a pane.
 * The messages are taken from the messenger right away, like the status
 * messages element does when it is rendered.
 */
trait PaneMessagesTrait {

  /**
   * Builds the notices from (and clears) the messages of the messenger.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   *
   * @return array
   *   The render array, empty if there is no message.
   */
  protected function buildPaneMessages(MessengerInterface $messenger): array {
    $messages = $messenger->all();
    $messenger->deleteAll();
    if (!$messages) {
      return [];
    }
    return [
      '#theme' => 'manager_pane_messages',
      '#messages' => $messages,
      '#cache' => ['max-age' => 0],
    ];
  }

}
