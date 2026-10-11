<?php

namespace Drupal\manager\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Asks the project manager page to reload the open pane of a project.
 */
class ManagerRefreshCommand implements CommandInterface {

  /**
   * Constructs the command.
   */
  public function __construct(protected int|string $projectId) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return ['command' => 'managerRefresh', 'projectId' => (string) $this->projectId];
  }

}
