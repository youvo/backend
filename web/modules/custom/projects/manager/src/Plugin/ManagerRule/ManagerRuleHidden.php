<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\manager\Attribute\ManagerRule;
use Drupal\projects\ProjectInterface;

/**
 * Provides a project hidden manager rule.
 */
#[ManagerRule(
  id: "hidden",
  category: RuleCategory::Supress,
  severity: RuleSeverity::Dormant,
  weight: 100,
)]
class ManagerRuleHidden extends ManagerRuleBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(ProjectInterface $project): bool {
    return $project->isPublished() === FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function text(ProjectInterface $project): TranslatableMarkup {
    return $this->t('The project is hidden.');
  }

}
