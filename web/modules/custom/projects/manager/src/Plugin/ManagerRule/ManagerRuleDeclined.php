<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\manager\Attribute\ManagerRule;
use Drupal\projects\ProjectInterface;

/**
 * Provides a project declined manager rule.
 */
#[ManagerRule(
  id: "declined",
  category: RuleCategory::Supress,
  severity: RuleSeverity::Declined,
  weight: 110,
)]
class ManagerRuleDeclined extends ManagerRuleBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(ProjectInterface $project): bool {
    return $project->lifecycle()->isDeclined();
  }

  /**
   * {@inheritdoc}
   */
  protected function text(ProjectInterface $project): TranslatableMarkup {
    return $this->t('The project was declined.');
  }

}
