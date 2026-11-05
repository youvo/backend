<?php

namespace Drupal\stats\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\stats\Activity\ActivitySnapshots;

/**
 * Hook implementations of the stats module.
 */
class StatsHooks {

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
