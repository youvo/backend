<?php

namespace Drupal\manager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\manager\Attribute\ManagerContextPane;
use Drupal\manager\Plugin\ManagerContextPane\ManagerContextPaneInterface;

/**
 * Provides manager context pane plugins.
 */
class ManagerContextPanes extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/ManagerContextPane',
      $namespaces,
      $module_handler,
      ManagerContextPaneInterface::class,
      ManagerContextPane::class
    );
    $this->alterInfo('manager_context_pane_info');
    $this->setCacheBackend($cache_backend, 'manager_context_pane_plugins');
  }

}
