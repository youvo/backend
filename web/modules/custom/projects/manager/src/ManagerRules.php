<?php

namespace Drupal\manager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\manager\Attribute\ManagerRule;
use Drupal\manager\Plugin\ManagerRule\ManagerRuleInterface;
use Drupal\projects\ProjectInterface;

/**
 * Provides manager context pane plugins.
 */
class ManagerRules extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/ManagerRule',
      $namespaces,
      $module_handler,
      ManagerRuleInterface::class,
      ManagerRule::class
    );
    $this->alterInfo('manager_rule_info');
    $this->setCacheBackend($cache_backend, 'manager_rule_plugins');
  }

  /**
   * Gets all rules that apply to the given project.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project.
   *
   * @return \Drupal\manager\Plugin\ManagerContextPane\ManagerRuleInterface[]
   *   An array of manager rules.
   */
  public function getRules(ProjectInterface $project): array {
    foreach ($this->getDefinitions() as $id => $definition) {
      $rule = $this->createInstance($id);
      if ($rule->applies($project)) {
        $rules[] = $rule;
      }
    }
    return $rules ?? [];
  }

}
