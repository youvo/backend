<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\projects\ProjectInterface;

/**
 * Defines an interface for manager rule plugins.
 */
interface ManagerRuleInterface {

  /**
   * Decides whether the rule applies to a project.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project entity.
   *
   * @return bool
   *   Whether the rule applies.
   */
  public function applies(ProjectInterface $project): bool;

  /**
   * Gets the category of the rule.
   */
  public function category(): RuleCategory;

  /**
   * Gets the severity of the rule.
   */
  public function severity(): RuleSeverity;

  /**
   * Builds the render array for the rule notification.
   *
   * @param \Drupal\projects\ProjectInterface $project
   *   The project entity.
   *
   * @return array
   *   A render array for the rule notification.
   */
  public function build(ProjectInterface $project): array;

}
