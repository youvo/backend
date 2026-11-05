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

}
