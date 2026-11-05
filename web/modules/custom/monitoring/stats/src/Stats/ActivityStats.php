<?php

namespace Drupal\stats\Stats;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\projects\ProjectTransition;
use Drupal\stats\StatsPeriod;
use Drupal\stats\StatsScope;

/**
 * Computes the stats of applications, creatives and organizations.
 *
 * The applicant of a project_apply log and the creatives of a
 * project_mediate log are its log__creatives. Log methods read the logs they
 * need beyond the period or scope, and apply the windows in PHP. The log
 * status is never filtered, because archived logs count too.
 */
class ActivityStats {

  /**
   * The log pattern of an application.
   */
  const string LOG_APPLY = 'project_apply';

  /**
   * The log pattern of a mediation.
   */
  const string LOG_MEDIATE = 'project_mediate';

  /**
   * The log patterns with a reason in misc.
   */
  const array REASON_LOGS = ['project_decline', 'project_archive'];

  /**
   * The days within which a new creative applies to count as activated.
   */
  const int ACTIVATION_DAYS = 30;

  /**
   * The upper bounds in days of the first application histogram.
   */
  const array HISTOGRAM = [
    '1d' => 1,
    '7d' => 7,
    '30d' => 30,
    '90d' => 90,
    '1y' => 365,
  ];

  /**
   * Constructs an ActivityStats object.
   */
  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Counts the applications and the applicants in the period.
   *
   * @return array{per_bucket: array<string, int>, active_per_bucket: array<string, int>, total: int, active: int, first_time: int}
   *   The applications and distinct applicants per bucket, their totals and
   *   the applicants whose first application ever lies in the period.
   */
  public function applications(StatsPeriod $period, StatsScope $scope): array {

    $empty = array_fill_keys(array_keys($period->buckets()), 0);
    $logs = [];
    $applicants = [];
    $bucket_applicants = [];
    $first = [];
    $organizations = $this->organizations($scope);

    // Applications before the period give the first application ever.
    $query = $this->logs([static::LOG_APPLY]);
    $query->condition('l.created', $period->to()->getTimestamp(), '<');
    foreach ($query->execute() as $row) {
      $creative = $row->creative === NULL ? NULL : (int) $row->creative;
      if ($creative !== NULL) {
        $first[$creative] ??= (int) $row->created;
      }
      $bucket = $period->bucketOf((int) $row->created);
      if ($bucket === NULL || !$this->inScope($row->organization, $organizations)) {
        continue;
      }
      $logs[$bucket][(int) $row->id] = TRUE;
      if ($creative !== NULL) {
        $applicants[$creative] = TRUE;
        $bucket_applicants[$bucket][$creative] = TRUE;
      }
    }

    $per_bucket = $empty;
    foreach ($logs as $bucket => $ids) {
      $per_bucket[$bucket] = count($ids);
    }
    $active_per_bucket = $empty;
    foreach ($bucket_applicants as $bucket => $creatives) {
      $active_per_bucket[$bucket] = count($creatives);
    }
    $first_time = 0;
    foreach (array_keys($applicants) as $creative) {
      $first_time += (int) $period->contains($first[$creative]);
    }

    return [
      'per_bucket' => $per_bucket,
      'active_per_bucket' => $active_per_bucket,
      'total' => array_sum($per_bucket),
      'active' => count($applicants),
      'first_time' => $first_time,
    ];
  }

  /**
   * Counts the distinct creatives of the mediations in the period.
   */
  public function engagedCreatives(StatsPeriod $period, StatsScope $scope): int {

    $organizations = $this->organizations($scope);
    $query = $this->logs([static::LOG_MEDIATE]);
    $query->condition('l.created', $period->from()->getTimestamp(), '>=')
      ->condition('l.created', $period->to()->getTimestamp(), '<');

    $creatives = [];
    foreach ($query->execute() as $row) {
      if ($row->creative !== NULL && $this->inScope($row->organization, $organizations)) {
        $creatives[(int) $row->creative] = TRUE;
      }
    }
    return count($creatives);
  }

  /**
   * Gets the share of rejected applicants who applied again.
   *
   * The rejected applicants of a mediation in the period are the applicants
   * of its project that it did not mediate. They came back if they applied
   * to any project after the mediation.
   *
   * @return float|null
   *   The share between 0 and 1, NULL if nobody was rejected.
   */
  public function returnAfterRejection(StatsPeriod $period, StatsScope $scope): ?float {

    $organizations = $this->organizations($scope);
    $applicants = [];
    $last_application = [];
    $mediations = [];
    foreach ($this->logs([static::LOG_APPLY, static::LOG_MEDIATE])->execute() as $row) {
      $created = (int) $row->created;
      $creative = $row->creative === NULL ? NULL : (int) $row->creative;
      if ($row->type === static::LOG_APPLY) {
        if ($creative !== NULL) {
          $applicants[(int) $row->project][$creative] = TRUE;
          $last_application[$creative] = max($last_application[$creative] ?? 0, $created);
        }
      }
      elseif ($period->contains($created) && $this->inScope($row->organization, $organizations)) {
        $mediations[(int) $row->id]['project'] = (int) $row->project;
        $mediations[(int) $row->id]['created'] = $created;
        $mediations[(int) $row->id]['creatives'] ??= [];
        if ($creative !== NULL) {
          $mediations[(int) $row->id]['creatives'][$creative] = TRUE;
        }
      }
    }

    $rejected = 0;
    $returned = 0;
    foreach ($mediations as $mediation) {
      foreach (array_keys($applicants[$mediation['project']] ?? []) as $creative) {
        if (isset($mediation['creatives'][$creative])) {
          continue;
        }
        $rejected++;
        $returned += (int) (($last_application[$creative] ?? 0) > $mediation['created']);
      }
    }
    return $rejected > 0 ? (float) $returned / $rejected : NULL;
  }

  /**
   * Gets the median days from a publish to the first application after it.
   *
   * @param \Drupal\stats\StatsPeriod $period
   *   The period.
   * @param \Drupal\stats\StatsScope $scope
   *   The scope.
   * @param array<int, array{project: int, ts: int}> $publishRows
   *   The publish rows in the period, from ProjectStats::publishRows().
   *
   * @return float|null
   *   The median in days with one decimal, NULL without applications.
   */
  public function firstApplicationMedian(StatsPeriod $period, StatsScope $scope, array $publishRows): ?float {

    $organizations = $this->organizations($scope);
    $projects = array_unique(array_column($publishRows, 'project'));
    if (!$projects || $organizations === []) {
      return NULL;
    }

    $query = $this->database->select('log', 'l')
      ->fields('l', ['project', 'organization', 'created'])
      ->condition('l.type', static::LOG_APPLY)
      ->condition('l.project', $projects, 'IN')
      ->condition('l.created', $period->from()->getTimestamp(), '>')
      ->orderBy('l.created')
      ->orderBy('l.id');
    $applications = [];
    foreach ($query->execute() as $row) {
      if ($this->inScope($row->organization, $organizations)) {
        $applications[(int) $row->project][] = (int) $row->created;
      }
    }

    $durations = [];
    foreach ($publishRows as $publish) {
      foreach ($applications[$publish['project']] ?? [] as $created) {
        if ($created > $publish['ts']) {
          $durations[] = $created - $publish['ts'];
          break;
        }
      }
    }
    $median = ProjectStats::median($durations);
    return $median === NULL ? NULL : round($median / 86400, 1);
  }

  /**
   * Counts the registrations per bucket, with running totals.
   *
   * @return array{creatives: array<string, int>, organizations: array<string, int>, creatives_total: array<string, int>, organizations_total: array<string, int>}
   *   The registrations per bucket, and all users registered before each
   *   bucket end.
   */
  public function registrations(StatsPeriod $period): array {

    $buckets = $period->buckets();
    $empty = array_fill_keys(array_keys($buckets), 0);
    $result = [
      'creatives' => $empty,
      'organizations' => $empty,
      'creatives_total' => $empty,
      'organizations_total' => $empty,
    ];
    $keys = ['user' => 'creatives', 'organization' => 'organizations'];

    $query = $this->database->select('users_field_data', 'u')
      ->fields('u', ['type', 'created'])
      ->condition('u.type', array_keys($keys), 'IN')
      ->condition('u.uid', 0, '>')
      ->condition('u.default_langcode', 1)
      ->condition('u.created', $period->to()->getTimestamp(), '<');
    $before = ['creatives' => 0, 'organizations' => 0];
    foreach ($query->execute() as $row) {
      $key = $keys[$row->type];
      $bucket = $period->bucketOf((int) $row->created);
      if ($bucket === NULL) {
        $before[$key]++;
      }
      else {
        $result[$key][$bucket]++;
      }
    }

    foreach ($before as $key => $total) {
      foreach ($result[$key] as $bucket => $count) {
        $total += $count;
        $result[$key . '_total'][$bucket] = $total;
      }
    }
    return $result;
  }

  /**
   * Counts the organizations created in the period through the funnel.
   *
   * @return array{created: int, managed: int, published: int}
   *   The organizations created, those of them with a manager, and those of
   *   them with a published project.
   */
  public function prospectFunnel(StatsPeriod $period): array {

    $published = $this->database->select('project__field_lifecycle_history', 'h');
    $published->innerJoin('project_field_data', 'p', 'p.id = h.entity_id AND p.langcode = h.langcode');
    $published->addField('p', 'uid');
    $published->condition('h.deleted', 0)
      ->condition('h.field_lifecycle_history_transition', ProjectTransition::Publish->value)
      ->distinct();

    $query = $this->database->select('users_field_data', 'u');
    $query->leftJoin('user__field_manager', 'm', 'm.entity_id = u.uid AND m.deleted = 0');
    $query->leftJoin($published, 'pub', 'pub.uid = u.uid');
    $query->addField('u', 'uid');
    $query->addField('m', 'field_manager_target_id', 'manager');
    $query->addField('pub', 'uid', 'published');
    $query->condition('u.type', 'organization')
      ->condition('u.default_langcode', 1)
      ->condition('u.created', $period->from()->getTimestamp(), '>=')
      ->condition('u.created', $period->to()->getTimestamp(), '<');

    $funnel = ['created' => 0, 'managed' => 0, 'published' => 0];
    foreach ($query->execute() as $row) {
      $funnel['created']++;
      if ($row->manager !== NULL) {
        $funnel['managed']++;
        $funnel['published'] += (int) ($row->published !== NULL);
      }
    }
    return $funnel;
  }

  /**
   * Gets the share of organizations publishing in the period that did before.
   *
   * @return float|null
   *   The share between 0 and 1, NULL if no organization published.
   */
  public function repeatOrganizations(StatsPeriod $period, StatsScope $scope): ?float {

    $organizations = $scope->organizationIds();
    if ($organizations === []) {
      return NULL;
    }

    $query = $this->database->select('project__field_lifecycle_history', 'h');
    $query->innerJoin('project_field_data', 'p', 'p.id = h.entity_id AND p.langcode = h.langcode');
    $query->addField('p', 'uid');
    $query->addField('h', 'field_lifecycle_history_timestamp', 'timestamp');
    $query->condition('h.deleted', 0)
      ->condition('h.field_lifecycle_history_transition', ProjectTransition::Publish->value)
      ->condition('h.field_lifecycle_history_timestamp', $period->to()->getTimestamp(), '<');
    if ($organizations !== NULL) {
      $query->condition('p.uid', $organizations, 'IN');
    }

    $in_period = [];
    $before = [];
    $from = $period->from()->getTimestamp();
    foreach ($query->execute() as $row) {
      if ((int) $row->timestamp >= $from) {
        $in_period[(int) $row->uid] = TRUE;
      }
      else {
        $before[(int) $row->uid] = TRUE;
      }
    }
    if (!$in_period) {
      return NULL;
    }
    return (float) count(array_intersect_key($in_period, $before)) / count($in_period);
  }

  /**
   * Counts the decline or archive logs in the period by reason.
   *
   * @param \Drupal\stats\StatsPeriod $period
   *   The period.
   * @param \Drupal\stats\StatsScope $scope
   *   The scope.
   * @param string $type
   *   The log pattern, project_decline or project_archive.
   *
   * @return array<string, int>
   *   The counts keyed by reason in alphabetical order. Logs without a reason
   *   count as "unknown".
   */
  public function reasons(StatsPeriod $period, StatsScope $scope, string $type): array {

    if (!in_array($type, static::REASON_LOGS, TRUE)) {
      throw new \InvalidArgumentException(sprintf('No reasons for the log pattern %s.', $type));
    }
    $organizations = $scope->organizationIds();
    if ($organizations === []) {
      return [];
    }

    $query = $this->database->select('log', 'l')
      ->fields('l', ['misc'])
      ->condition('l.type', $type)
      ->condition('l.created', $period->from()->getTimestamp(), '>=')
      ->condition('l.created', $period->to()->getTimestamp(), '<');
    if ($organizations !== NULL) {
      $query->condition('l.organization', $organizations, 'IN');
    }

    $reasons = [];
    foreach ($query->execute() as $row) {
      $misc = Json::decode($row->misc ?? '') ?? [];
      $reason = is_string($misc['reason'] ?? NULL) ? $misc['reason'] : 'unknown';
      $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
    }
    ksort($reasons);
    return $reasons;
  }

  /**
   * Counts the creatives registered and activated per bucket.
   *
   * A creative is activated by a first application within 30 days of the
   * registration.
   *
   * @return array{registered: array<string, int>, activated: array<string, int>, rate: ?float}
   *   The counts by registration bucket, and the activated share of the
   *   period, NULL if nobody registered.
   */
  public function activation(StatsPeriod $period): array {

    $registered = array_fill_keys(array_keys($period->buckets()), 0);
    $activated = $registered;
    foreach ($this->firstApplications($period) as [$created, $first]) {
      $bucket = $period->bucketOf($created);
      if ($bucket === NULL) {
        continue;
      }
      $registered[$bucket]++;
      if ($first !== NULL && $first - $created <= static::ACTIVATION_DAYS * 86400) {
        $activated[$bucket]++;
      }
    }

    $total = array_sum($registered);
    return [
      'registered' => $registered,
      'activated' => $activated,
      'rate' => $total > 0 ? (float) array_sum($activated) / $total : NULL,
    ];
  }

  /**
   * Counts the creatives registered in the period by days to apply first.
   *
   * @return array<string, int>
   *   The counts keyed by 1d, 7d, 30d, 90d, 1y (at most that many days),
   *   later and never.
   */
  public function firstApplicationHistogram(StatsPeriod $period): array {

    $histogram = array_fill_keys([...array_keys(static::HISTOGRAM), 'later', 'never'], 0);
    foreach ($this->firstApplications($period) as [$created, $first]) {
      if ($first === NULL) {
        $histogram['never']++;
        continue;
      }
      $days = ($first - $created) / 86400;
      $key = 'later';
      foreach (static::HISTOGRAM as $bound => $limit) {
        if ($days <= $limit) {
          $key = $bound;
          break;
        }
      }
      $histogram[$key]++;
    }
    return $histogram;
  }

  /**
   * Gets the creatives registered in the period with their first application.
   *
   * @return array<int, array{0: int, 1: ?int}>
   *   The registration and first application timestamps, keyed by uid.
   */
  protected function firstApplications(StatsPeriod $period): array {

    $first = $this->database->select('log', 'l');
    $first->innerJoin('log__creatives', 'c', 'c.entity_id = l.id AND c.deleted = 0');
    $first->addField('c', 'creatives_target_id', 'creative');
    $first->addExpression('MIN(l.created)', 'first');
    $first->condition('l.type', static::LOG_APPLY)
      ->groupBy('c.creatives_target_id');

    $query = $this->database->select('users_field_data', 'u');
    $query->leftJoin($first, 'f', 'f.creative = u.uid');
    $query->addField('u', 'uid');
    $query->addField('u', 'created');
    $query->addField('f', 'first');
    $query->condition('u.type', 'user')
      ->condition('u.uid', 0, '>')
      ->condition('u.default_langcode', 1)
      ->condition('u.created', $period->from()->getTimestamp(), '>=')
      ->condition('u.created', $period->to()->getTimestamp(), '<');

    $creatives = [];
    foreach ($query->execute() as $row) {
      $creatives[(int) $row->uid] = [(int) $row->created, $row->first === NULL ? NULL : (int) $row->first];
    }
    return $creatives;
  }

  /**
   * Builds the query of logs with one row per creative, ordered by time.
   *
   * Logs without creatives give one row with a NULL creative.
   *
   * @param string[] $types
   *   The log patterns.
   */
  protected function logs(array $types): SelectInterface {
    $query = $this->database->select('log', 'l');
    $query->leftJoin('log__creatives', 'c', 'c.entity_id = l.id AND c.deleted = 0');
    $query->fields('l', ['id', 'type', 'created', 'organization', 'project']);
    $query->addField('c', 'creatives_target_id', 'creative');
    return $query->condition('l.type', $types, 'IN')
      ->orderBy('l.created')
      ->orderBy('l.id');
  }

  /**
   * Gets the organizations of a scope as keys, NULL for all.
   *
   * @return array<int, true>|null
   *   The organization ids as keys.
   */
  protected function organizations(StatsScope $scope): ?array {
    $ids = $scope->organizationIds();
    return $ids === NULL ? NULL : array_fill_keys($ids, TRUE);
  }

  /**
   * Checks whether a log organization lies in the scope.
   *
   * @param int|string|null $organization
   *   The organization of the log.
   * @param array<int, true>|null $organizations
   *   The organizations of the scope as keys, NULL for all.
   */
  protected function inScope(int|string|null $organization, ?array $organizations): bool {
    return $organizations === NULL || ($organization !== NULL && isset($organizations[(int) $organization]));
  }

}
