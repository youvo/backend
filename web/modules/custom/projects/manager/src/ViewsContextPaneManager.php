<?php

namespace Drupal\manager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\manager\Attribute\ViewsContextPane;
use Drupal\manager\Plugin\ViewsContextPane\ContextPanePluginInterface;

/**
 *
 */
class ViewsContextPaneManager extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/ViewsContextPane',
      $namespaces,
      $module_handler,
      ContextPanePluginInterface::class,
      ViewsContextPane::class
    );
    $this->alterInfo('views_context_pane_info');
    $this->setCacheBackend($cache_backend, 'views_context_pane_plugins');
  }

}
