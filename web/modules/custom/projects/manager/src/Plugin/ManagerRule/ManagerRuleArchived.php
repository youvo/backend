<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\manager\Attribute\ManagerRule;
use Drupal\projects\ProjectInterface;

/**
 * Provides a project archived manager rule.
 */
#[ManagerRule(
  id: "archived",
  category: RuleCategory::Supress,
  severity: RuleSeverity::Archived,
  weight: 110,
)]
class ManagerRuleArchived extends ManagerRuleBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(ProjectInterface $project): bool {
    return $project->lifecycle()->isArchived();
  }

  /**
   * {@inheritdoc}
   */
  protected function text(ProjectInterface $project): TranslatableMarkup {
    return $this->t('The project is archived.');
  }

}
