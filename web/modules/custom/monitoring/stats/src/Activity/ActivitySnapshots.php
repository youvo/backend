<?php

namespace Drupal\stats\Activity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drupal\stats\StatsPeriod;

/**
 * Records and reads the daily snapshots of active users per role.
 */
class ActivitySnapshots {

  /**
   * The activity roles.
   */
  const array ROLES = ['creative', 'organization', 'prospect'];

  /**
   * The roles that make a creative a staff member.
   */
  const array STAFF_ROLES = [
    'manager',
    'supervisor',
    'observer',
    'administrator',
    'editor',
    'configurator',
  ];

  /**
   * The columns of the active users, keyed by the granularity reading them.
   *
   * They count the users active since the start of the day, the ISO week, the
   * month, the quarter and the year, up to the end of the day. No granularity
   * reads the quarter; the tiles of a quarter do.
   */
  const array COLUMNS = [
    'day' => 'active_day',
    'week' => 'active_week',
    'month' => 'active_month',
    'quarter' => 'active_quarter',
    'year' => 'active_year',
  ];

  /**
   * The state key of the last recorded day.
   */
  const string STATE_LAST_DAY = 'stats.activity_last_day';

  public function __construct(
    protected Connection $database,
    protected TimeInterface $time,
    protected ConfigFactoryInterface $configFactory,
    protected StateInterface $state,
  ) {}

  /**
   * Counts the users per activity role with an access since a timestamp.
   *
   * @return array<string, int>
   *   The counts keyed by role.
   */
  public function countActive(int $since): array {

    $creatives = $this->database->select('users_field_data', 'u');
    $creatives->join('user__roles', 'r', 'r.entity_id = u.uid');
    $creatives->condition('u.type', 'user')
      ->condition('r.roles_target_id', 'creative')
      ->condition('u.access', $since, '>=');
    $staff = $this->database->select('user__roles', 's');
    $staff->addExpression('1');
    $staff->where('s.entity_id = u.uid')
      ->condition('s.roles_target_id', static::STAFF_ROLES, 'IN');
    $creatives->notExists($staff);
    $creatives->addExpression('COUNT(DISTINCT u.uid)');

    $organizations = $this->database->select('users_field_data', 'u');
    $organizations->join('user__roles', 'r', 'r.entity_id = u.uid');
    $organizations->condition('u.type', 'organization')
      ->condition('r.roles_target_id', 'organization')
      ->condition('u.access', $since, '>=');
    $organizations->addExpression('COUNT(DISTINCT u.uid)');

    $prospects = $this->database->select('users_field_data', 'u');
    $prospects->join('user__roles', 'r', 'r.entity_id = u.uid');
    $prospects->condition('u.type', 'organization')
      ->condition('r.roles_target_id', 'prospect')
      ->condition('u.access', $since, '>=');
    $prospects->addExpression('COUNT(DISTINCT u.uid)');

    return [
      'creative' => (int) $creatives->execute()->fetchField(),
      'organization' => (int) $organizations->execute()->fetchField(),
      'prospect' => (int) $prospects->execute()->fetchField(),
    ];
  }

  /**
   * Writes the snapshot of yesterday once per local calendar day.
   *
   * The users table keeps only the last access of each user, so a user counts
   * as active since a start if the last access is not before it. That
   * includes users whose only visit since was today, before cron ran: a small
   * overcount. A day on which cron does not run after midnight is skipped for
   * good, as users.access cannot be read back for past days.
   *
   * @return bool
   *   FALSE when yesterday is already stored.
   */
  public function recordDaily(): bool {

    $now = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($this->timezone());
    $start = $now->setTime(0, 0)->modify('-1 day');
    $day = $start->format('Y-m-d');
    if ($this->state->get(static::STATE_LAST_DAY) === $day) {
      return FALSE;
    }

    $year = (int) $start->format('Y');
    $month = (int) $start->format('n');
    $starts = [
      'active_day' => $start,
      'active_week' => $start->modify('-' . ((int) $start->format('N') - 1) . ' days')->setTime(0, 0),
      'active_month' => $start->setDate($year, $month, 1)->setTime(0, 0),
      'active_quarter' => $start->setDate($year, $month - ($month - 1) % 3, 1)->setTime(0, 0),
      'active_year' => $start->setDate($year, 1, 1)->setTime(0, 0),
    ];
    $counts = [];
    foreach ($starts as $column => $since) {
      foreach ($this->countActive($since->getTimestamp()) as $role => $count) {
        $counts[$role][$column] = $count;
      }
    }
    foreach (static::ROLES as $role) {
      $this->write($day, $role, $counts[$role]);
    }
    $this->state->set(static::STATE_LAST_DAY, $day);
    return TRUE;
  }

  /**
   * Writes the row of a day and role, replacing an existing one.
   *
   * @param string $day
   *   The day, Y-m-d.
   * @param string $role
   *   The activity role.
   * @param array{active_day: int, active_week: int, active_month: int, active_quarter: int, active_year: int} $counts
   *   The active users since the start of the day, week, month, quarter and
   *   year.
   */
  public function write(string $day, string $role, array $counts): void {
    $fields = [];
    foreach (static::COLUMNS as $column) {
      $fields[$column] = $counts[$column];
    }
    $this->database->merge('stats_activity')
      ->keys(['day' => $day, 'role' => $role])
      ->fields($fields)
      ->execute();
  }

  /**
   * Gets the active users per role and bucket.
   *
   * A bucket holds the value of its last stored day in the column of the
   * granularity: the users active on a day, in a week, a month or a year up
   * to that day. Buckets without a stored day, as those in the future, hold
   * NULL. A bucket clipped by the period, such as the first month of a range
   * starting mid-month, still counts from the start of its week, month or
   * year: a slight overcount at the edges.
   *
   * @return array<string, array<string, ?int>>
   *   The values keyed by role, then bucket.
   */
  public function series(StatsPeriod $period): array {

    $column = static::COLUMNS[$period->granularity()->value];
    $series = array_fill_keys(static::ROLES, $period->emptyBuckets(NULL));
    $rows = $this->database->select('stats_activity', 'a')
      ->fields('a', ['day', 'role', $column])
      ->condition('a.day', $period->from()->format('Y-m-d'), '>=')
      ->condition('a.day', $period->to()->format('Y-m-d'), '<')
      ->condition('a.role', static::ROLES, 'IN')
      ->orderBy('a.day')
      ->execute();

    $timezone = $period->from()->getTimezone();
    foreach ($rows as $row) {
      $bucket = $period->bucketOf((new \DateTimeImmutable($row->day, $timezone))->getTimestamp());
      if ($bucket !== NULL) {
        $series[$row->role][$bucket] = (int) $row->{$column};
      }
    }
    return $series;
  }

  /**
   * Gets the site timezone.
   */
  public function timezone(): \DateTimeZone {
    return new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default'));
  }

}
