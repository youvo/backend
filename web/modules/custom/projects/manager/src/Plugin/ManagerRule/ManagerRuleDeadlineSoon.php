<?php

namespace Drupal\manager\Plugin\ManagerRule;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\manager\Attribute\ManagerRule;
use Drupal\projects\ProjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a deadline soon manager rule.
 */
#[ManagerRule(
  id: "deadline_soon",
  category: RuleCategory::Deadline,
  severity: RuleSeverity::Warning,
)]
class ManagerRuleDeadlineSoon extends ManagerRuleBase {

  use StringTranslationTrait;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, ...$defaults): static {
    $instance = parent::create($container, ...$defaults);
    $instance->time = $container->get('datetime.time');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(ProjectInterface $project): bool {
    if ($project->lifecycle()->isCompleted() || $project->get(ProjectInterface::FIELD_DEADLINE)->isEmpty()) {
      return FALSE;
    }
    $current_time = DrupalDateTime::createFromTimestamp($this->time->getCurrentTime());
    $deadline = DrupalDateTime::createFromFormat('Y-m-d', $project->get(ProjectInterface::FIELD_DEADLINE)->value);
    return $current_time > $deadline->sub(new \DateInterval('P14D'));
  }

  /**
   * {@inheritdoc}
   */
  protected function text(ProjectInterface $project): TranslatableMarkup {
    return $this->t('The project deadline is upcoming.');
  }

}
