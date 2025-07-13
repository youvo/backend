<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\projects\Entity\Project;

/**
 * Defines an interface for manager rule plugins.
 */
interface ManagerRuleInterface {

  /**
   * Decides whether the rule applies to a project.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project entity.
   *
   * @return bool
   *   Whether the rule applies.
   */
  public function applies(Project $project): bool;

  /**
   * Gets the type of the rule.
   */
  public function status(): RuleStatus;

  /**
   * Gets the priority of the rule.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project entity.
   *
   * @return int
   *   The priority of the rule.
   */
  public function priority(Project $project): int;

  /**
   * Builds the render array for the rule notification.
   *
   * @param \Drupal\projects\Entity\Project $project
   *   The project entity.
   *
   * @return array
   *   A render array for the rule notification.
   */
  public function build(Project $project): array;

}
