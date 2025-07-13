<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\manager\Attribute\ManagerRule;
use Drupal\projects\ProjectInterface;

/**
 * Provides an passed deadline manager rule.
 */
#[ManagerRule(
  id: "applicants",
  category: RuleCategory::Other,
  severity: RuleSeverity::Normal,
  weight: 10,
)]
class ManagerRuleApplicants extends ManagerRuleBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(ProjectInterface $project): bool {
    return $project->lifecycle()->isOpen() && $project->hasApplicant();
  }

  /**
   * {@inheritdoc}
   */
  protected function text(ProjectInterface $project): TranslatableMarkup {
    return $this->t('The project has @count applicant(s).', ['@count' => count($project->getApplicants())]);
  }

}
