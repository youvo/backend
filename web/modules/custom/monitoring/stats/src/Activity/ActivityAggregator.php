<?php

namespace Drupal\stats\Activity;

/**
 * Aggregates visits into daily active users per role.
 */
class ActivityAggregator {

  /**
   * Aggregates visits into the active users per role and day.
   *
   * Each day counts the distinct users of a role who visited on the day, in
   * its ISO week up to the day (from Monday), in its month up to the day
   * (from the 1st), in its quarter up to the day (from the 1st of January,
   * April, July or October) and in its year up to the day. Each role
   * gets a row for every day from its own first visit day to the last visit
   * day of all visits, including days without visits.
   *
   * @param iterable<array{0: int, 1: string, 2: int}> $visits
   *   The visits as [uid, role, timestamp], in any order.
   * @param \DateTimeZone $timezone
   *   The timezone of the days.
   *
   * @return array<string, array<string, array{active_day: int, active_week: int, active_month: int, active_quarter: int, active_year: int}>>
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

    // Walk each role's days and collect the visitors since the start of the
    // week, month, quarter and year. Days are calendar dates, so they are
    // walked in UTC to skip DST shifts.
    $utc = new \DateTimeZone('UTC');
    $rows = [];
    foreach ($visitors as $role => $days) {
      ksort($days);
      $week = [];
      $month = [];
      $quarter = [];
      $year = [];
      $date = new \DateTimeImmutable(array_key_first($days), $utc);
      $end = new \DateTimeImmutable($last_day, $utc);
      while ($date <= $end) {
        $day = $date->format('Y-m-d');
        if ($date->format('N') === '1') {
          $week = [];
        }
        if ($date->format('j') === '1') {
          $month = [];
          $month_number = (int) $date->format('n');
          if ($month_number % 3 === 1) {
            $quarter = [];
          }
          if ($month_number === 1) {
            $year = [];
          }
        }
        $visited = $days[$day] ?? [];
        $week += $visited;
        $month += $visited;
        $quarter += $visited;
        $year += $visited;
        $rows[$day][$role] = [
          'active_day' => count($visited),
          'active_week' => count($week),
          'active_month' => count($month),
          'active_quarter' => count($quarter),
          'active_year' => count($year),
        ];
        $date = $date->modify('+1 day');
      }
    }
    ksort($rows);
    return $rows;
  }

}
