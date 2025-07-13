<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\manager\Attribute\ManagerRule;
use Drupal\projects\Entity\Project;

/**
 * Provides a project hidden manager rule.
 */
#[ManagerRule(id: "hidden")]
class ManagerRuleHidden extends ManagerRuleBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function status(): RuleStatus {
    return RuleStatus::Inactive;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Project $project): bool {
    return $project->isPublished() === FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function priority(Project $project): int {
    return 100;
  }

  /**
   * {@inheritdoc}
   */
  public function build(Project $project): array {

    return [
      '#theme' => 'manager_rule',
      '#type' => 'hidden',
      '#project' => $project,
      'content' => [
        'status' => $this->status()->value,
        'text' => 'This is project is hidden.',
      ],
    ];
  }

}
