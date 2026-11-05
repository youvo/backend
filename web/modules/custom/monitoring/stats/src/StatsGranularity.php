<?php

namespace Drupal\stats;

/**
 * Defines the bucket sizes of a stats period.
 */
enum StatsGranularity: string {

  case Day = 'day';
  case Week = 'week';
  case Month = 'month';
  case Year = 'year';

}
