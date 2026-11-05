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

    $active_1d = $this->countActive($start->getTimestamp());
    $active_30d = $this->countActive($start->modify('-29 days')->getTimestamp());
    foreach (static::ROLES as $role) {
      $this->write($day, $role, $active_1d[$role], $active_30d[$role]);
    }
    $this->state->set(static::STATE_LAST_DAY, $day);
    return TRUE;
  }

  /**
   * Writes the row of a day and role, replacing an existing one.
   */
  public function write(string $day, string $role, int $active1d, int $active30d): void {
    $this->database->merge('stats_activity')
      ->keys(['day' => $day, 'role' => $role])
      ->fields(['active_1d' => $active1d, 'active_30d' => $active30d])
      ->execute();
  }

  /**
   * Gets the active users per role and bucket of a period.
   *
   * A bucket holds the average active_1d over its stored days and the
   * active_30d of its last stored day. Buckets without rows hold NULL.
   *
   * @return array<string, array<string, array{active_1d: ?float, active_30d: ?int}>>
   *   The values keyed by role, then bucket.
   */
  public function series(StatsPeriod $period): array {

    $empty = array_fill_keys(array_keys($period->buckets()), ['active_1d' => NULL, 'active_30d' => NULL]);
    $series = array_fill_keys(static::ROLES, $empty);

    $rows = $this->database->select('stats_activity', 'a')
      ->fields('a', ['day', 'role', 'active_1d', 'active_30d'])
      ->condition('a.day', $period->from()->format('Y-m-d'), '>=')
      ->condition('a.day', $period->to()->format('Y-m-d'), '<')
      ->condition('a.role', static::ROLES, 'IN')
      ->orderBy('a.day')
      ->execute();

    $timezone = $period->from()->getTimezone();
    $sums = [];
    foreach ($rows as $row) {
      $bucket = $period->bucketOf((new \DateTimeImmutable($row->day, $timezone))->getTimestamp());
      if ($bucket === NULL) {
        continue;
      }
      $sums[$row->role][$bucket]['sum'] = ($sums[$row->role][$bucket]['sum'] ?? 0) + (int) $row->active_1d;
      $sums[$row->role][$bucket]['days'] = ($sums[$row->role][$bucket]['days'] ?? 0) + 1;
      $series[$row->role][$bucket]['active_30d'] = (int) $row->active_30d;
    }
    foreach ($sums as $role => $buckets) {
      foreach ($buckets as $bucket => $sum) {
        $series[$role][$bucket]['active_1d'] = (float) $sum['sum'] / $sum['days'];
      }
    }
    return $series;
  }

  /**
   * Gets the site timezone.
   */
  protected function timezone(): \DateTimeZone {
    return new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default'));
  }

}
