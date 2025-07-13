<?php

namespace Drupal\manager\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\manager\Plugin\ManagerRule\RuleCategory;
use Drupal\manager\Plugin\ManagerRule\RuleSeverity;

/**
 * Defines the ManagerRule plugin attribute.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class ManagerRule extends Plugin {

  /**
   * Constructs a ManagerRule attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\manager\Plugin\ManagerRule\RuleCategory $category
   *   The category of the manager rule. Usually, only one notification in each
   *   category is displayed. A critical rule can therefore supersede a warning.
   * @param \Drupal\manager\Plugin\ManagerRule\RuleSeverity $severity
   *   The severity of the manager rule.
   * @param int|null $weight
   *   (optional) An integer to determine the weight of this manager rule.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly RuleCategory $category = RuleCategory::Other,
    public readonly RuleSeverity $severity = RuleSeverity::Normal,
    public readonly ?int $weight = NULL,
    public readonly ?string $deriver = NULL,
  ) {}

}
