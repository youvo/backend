<?php

namespace Drupal\stats\Activity;

/**
 * Aggregates visits into daily active users per role.
 */
class ActivityAggregator {

  /**
   * Aggregates visits into daily and 30-day active users per role.
   *
   * Each role gets a row for every day from its own first visit day to the
   * last visit day of all visits, including days without visits.
   *
   * @param iterable<array{0: int, 1: string, 2: int}> $visits
   *   The visits as [uid, role, timestamp], in any order.
   * @param \DateTimeZone $timezone
   *   The timezone of the days.
   *
   * @return array<string, array<string, array{active_1d: int, active_30d: int}>>
   *   The rows keyed by day (Y-m-d), then role.
   */
  public static function aggregate(iterable $visits, \DateTimeZone $timezone): array {

    // Collect the visitors per role and local day.
    $visitors = [];
    $last_day = NULL;
    foreach ($visits as [$uid, $role, $timestamp]) {
      $day = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone)->format('Y-m-d');
      $visitors[$role][$day][$uid] = TRUE;
      if ($last_day === NULL || $day > $last_day) {
        $last_day = $day;
      }
    }

    // Walk each role's days with a 30-day window of visit days per user. Days
    // are calendar dates, so they are counted in UTC to skip DST shifts.
    $utc = new \DateTimeZone('UTC');
    $rows = [];
    foreach ($visitors as $role => $days) {
      ksort($days);
      $window = [];
      $date = new \DateTimeImmutable(array_key_first($days), $utc);
      $end = new \DateTimeImmutable($last_day, $utc);
      while ($date <= $end) {
        $day = $date->format('Y-m-d');
        foreach ($days[$day] ?? [] as $uid => $visited) {
          $window[$uid] = ($window[$uid] ?? 0) + 1;
        }
        $dropped = $date->modify('-30 days')->format('Y-m-d');
        foreach ($days[$dropped] ?? [] as $uid => $visited) {
          if (--$window[$uid] === 0) {
            unset($window[$uid]);
          }
        }
        $rows[$day][$role] = [
          'active_1d' => count($days[$day] ?? []),
          'active_30d' => count($window),
        ];
        $date = $date->modify('+1 day');
      }
    }
    ksort($rows);
    return $rows;
  }

}
