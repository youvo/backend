<?php

namespace Drupal\manager;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\manager\Attribute\ManagerRule;
use Drupal\manager\Plugin\ManagerRule\ManagerRuleInterface;
use Drupal\manager\Plugin\ManagerRule\RuleCategory;
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
   * @return \Drupal\manager\Plugin\ManagerRule\ManagerRuleInterface[]
   *   An array of manager rules.
   */
  public function getRules(ProjectInterface $project): array {

    foreach ($this->getDefinitions() as $id => $definition) {
      /** @var \Drupal\manager\Plugin\ManagerRule\ManagerRuleInterface $rule */
      $rule = $this->createInstance($id);
      if ($rule->applies($project)) {
        if ($rule->category() === RuleCategory::Supress) {
          $surpressing_rule = $rule;
          break;
        }
        $rules[] = $rule;
      }
    }

    // Only output one rule if it surpresses all others.
    if (isset($surpressing_rule)) {
      return [$surpressing_rule];
    }

    if (empty($rules)) {
      return [];
    }

    $selected_rules = [];
    foreach (RuleCategory::cases() as $category) {
      $rules_by_category = array_filter($rules, static fn($r) =>
        $r->category() === $category
      );
      if (!empty($rules_by_category)) {
        // Collect all rules in the category "Other".
        if ($category === RuleCategory::Other) {
          foreach ($rules_by_category as $rule) {
            $selected_rules[] = $rule;
          }
          continue;
        }
        // Only collect one rule per category elsewhen.
        $selected_rules[] = array_shift($rules_by_category);
      }
    }

    return $selected_rules;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions(): array {
    $definitions = parent::getDefinitions();
    uasort($definitions, [SortArray::class, 'sortByWeightElement']);
    return array_reverse($definitions);
  }

}
