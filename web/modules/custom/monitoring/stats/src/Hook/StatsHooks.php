<?php

namespace Drupal\stats\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\stats\Activity\ActivitySnapshots;

/**
 * Hook implementations of the stats module.
 */
class StatsHooks {

  use StringTranslationTrait;

  public function __construct(protected ActivitySnapshots $activitySnapshots) {}

  /**
   * Implements hook_cron().
   *
   * Records the active users of yesterday once per day.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->activitySnapshots->recordDaily();
  }

  /**
   * Implements hook_runtime_requirements().
   *
   * Warns on the status report when cron has not recorded the active users
   * for more than two days. Days without a cron run cannot be recorded later.
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {

    if (!$this->activitySnapshots->isBehind()) {
      return [];
    }

    $last_day = $this->activitySnapshots->lastDay();
    return [
      'stats_activity' => [
        'title' => $this->t('Stats activity snapshots'),
        'value' => $last_day !== NULL ? $this->t('Last recorded day: @day', ['@day' => $last_day]) : $this->t('No day recorded'),
        'description' => $this->t('Cron records the active users of the previous day once a day. Run cron daily, for example drush cron from a crontab. Days without a cron run are missing from the stats for good.'),
        'severity' => RequirementSeverity::Warning,
      ],
    ];
  }

  /**
   * Implements hook_gin_ignore_sticky_form_actions().
   *
   * The range form keeps its button next to the dates: Gin must not move it
   * into a sticky bar (and add a hidden link to the tab order).
   *
   * @return string[]
   *   The ids of the forms.
   */
  #[Hook('gin_ignore_sticky_form_actions')]
  public function ginIgnoreStickyFormActions(): array {
    return ['stats_range_form'];
  }

}
