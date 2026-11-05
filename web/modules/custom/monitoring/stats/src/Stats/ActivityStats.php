<?php

namespace Drupal\stats\Stats;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\projects\ProjectTransition;
use Drupal\stats\StatsPeriod;

/**
 * Computes the stats of applications, creatives and organizations.
 *
 * The applicant of a project_apply log and the creatives of a
 * project_mediate log are its log__creatives. Log methods read the logs they
 * need beyond the period, and apply the windows in PHP. The log
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
   * The application and mediation log rows, once read in this request.
   *
   * Several metrics of the page need them. They are read for all time, and
   * the methods apply the windows.
   *
   * @var array<int, object{id: int, type: string, created: int, project: ?int, creative: ?int}>|null
   */
  protected ?array $logRows = NULL;

  /**
   * The creatives registered in a period with their first application.
   *
   * @var array<string, array<int, array{0: int, 1: ?int}>>
   *   The results of firstApplications(), keyed by the period timestamps.
   */
  protected array $firstApplications = [];

  /**
   * Constructs an ActivityStats object.
   */
  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Counts the applications and the applicants in the period.
   *
   * @return array{per_bucket: array<string, int>, active_per_bucket: array<string, int>, active: int, first_time: int}
   *   The applications and distinct applicants per bucket, the distinct
   *   applicants of the period and those whose first application ever lies
   *   in it.
   */
  public function applications(StatsPeriod $period): array {

    $logs = [];
    $applicants = [];
    $bucket_applicants = [];
    $first = [];

    // Applications before the period give the first application ever.
    $to = $period->to()->getTimestamp();
    foreach ($this->logRows() as $row) {
      if ($row->created >= $to) {
        break;
      }
      if ($row->type !== static::LOG_APPLY) {
        continue;
      }
      $creative = $row->creative;
      if ($creative !== NULL) {
        $first[$creative] ??= $row->created;
      }
      if (!$period->contains($row->created)) {
        continue;
      }
      $bucket = $period->bucketOf($row->created);
      $logs[$bucket][$row->id] = TRUE;
      if ($creative !== NULL) {
        $applicants[$creative] = TRUE;
        $bucket_applicants[$bucket][$creative] = TRUE;
      }
    }

    $per_bucket = $period->emptyBuckets();
    foreach ($logs as $bucket => $ids) {
      $per_bucket[$bucket] = count($ids);
    }
    $active_per_bucket = $period->emptyBuckets();
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
      'active' => count($applicants),
      'first_time' => $first_time,
    ];
  }

  /**
   * Counts the distinct creatives of the mediations in the period.
   */
  public function engagedCreatives(StatsPeriod $period): int {
    $creatives = [];
    foreach ($this->logRows() as $row) {
      if ($row->type === static::LOG_MEDIATE && $row->creative !== NULL && $period->contains($row->created)) {
        $creatives[$row->creative] = TRUE;
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
  public function returnAfterRejection(StatsPeriod $period): ?float {

    $applicants = [];
    $last_application = [];
    $mediations = [];
    foreach ($this->logRows() as $row) {
      $created = $row->created;
      $creative = $row->creative;
      if ($row->type === static::LOG_APPLY) {
        if ($creative !== NULL) {
          $applicants[(int) $row->project][$creative] = TRUE;
          $last_application[$creative] = max($last_application[$creative] ?? 0, $created);
        }
      }
      elseif ($period->contains($created)) {
        $mediations[$row->id]['project'] = (int) $row->project;
        $mediations[$row->id]['created'] = $created;
        $mediations[$row->id]['creatives'] ??= [];
        if ($creative !== NULL) {
          $mediations[$row->id]['creatives'][$creative] = TRUE;
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
   * Counts the users registered before each bucket end.
   *
   * The users count as the totals of StatsCalculator count them: creatives
   * who have logged in, and organizations with the organization role, so
   * prospects are left out. The registrations and activation chart counts
   * all new creatives in activation() instead.
   *
   * @return array{creatives: array<string, int>, organizations: array<string, int>}
   *   The running totals per bucket.
   */
  public function registrations(StatsPeriod $period): array {

    $per_bucket = [
      'creatives' => $period->emptyBuckets(),
      'organizations' => $period->emptyBuckets(),
    ];
    $keys = ['user' => 'creatives', 'organization' => 'organizations'];

    $query = $this->database->select('users_field_data', 'u');
    $query->leftJoin('user__roles', 'r', 'r.entity_id = u.uid AND r.deleted = 0 AND r.roles_target_id = :role', [':role' => 'organization']);
    $query->fields('u', ['type', 'created']);
    $query->condition('u.uid', 0, '>')
      ->condition('u.default_langcode', 1)
      ->condition('u.created', $period->to()->getTimestamp(), '<');
    $creatives = $query->andConditionGroup()
      ->condition('u.type', 'user')
      ->condition('u.access', 0, '>');
    $organizations = $query->andConditionGroup()
      ->condition('u.type', 'organization')
      ->isNotNull('r.entity_id');
    $query->condition($query->orConditionGroup()
      ->condition($creatives)
      ->condition($organizations));
    $totals = ['creatives' => 0, 'organizations' => 0];
    foreach ($query->execute() as $row) {
      $key = $keys[$row->type];
      if ($period->contains((int) $row->created)) {
        $per_bucket[$key][$period->bucketOf((int) $row->created)]++;
      }
      else {
        $totals[$key]++;
      }
    }

    $result = [];
    foreach ($per_bucket as $key => $counts) {
      foreach ($counts as $bucket => $count) {
        $totals[$key] += $count;
        $result[$key][$bucket] = $totals[$key];
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
    $published->innerJoin('project_field_data', 'p', 'p.id = h.entity_id AND p.langcode = h.langcode AND p.default_langcode = 1');
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
   * Counts the decline or archive logs in the period by reason.
   *
   * @param \Drupal\stats\StatsPeriod $period
   *   The period.
   * @param string $type
   *   The log pattern, project_decline or project_archive.
   *
   * @return array<string, int>
   *   The counts keyed by reason in alphabetical order. Logs without a reason
   *   count as "unknown".
   */
  public function reasons(StatsPeriod $period, string $type): array {

    if (!in_array($type, static::REASON_LOGS, TRUE)) {
      throw new \InvalidArgumentException(sprintf('No reasons for the log pattern %s.', $type));
    }

    $query = $this->database->select('log', 'l')
      ->fields('l', ['misc'])
      ->condition('l.type', $type)
      ->condition('l.created', $period->from()->getTimestamp(), '>=')
      ->condition('l.created', $period->to()->getTimestamp(), '<');

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

    $registered = $period->emptyBuckets();
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

    $key = $period->from()->getTimestamp() . ':' . $period->to()->getTimestamp();
    if (isset($this->firstApplications[$key])) {
      return $this->firstApplications[$key];
    }

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
    $this->firstApplications[$key] = $creatives;
    return $creatives;
  }

  /**
   * Gets the application and mediation logs, one row per creative, by time.
   *
   * Logs without creatives give one row with a NULL creative. The rows are
   * read once per request.
   *
   * @return array<int, object{id: int, type: string, created: int, project: ?int, creative: ?int}>
   *   The rows ordered by creation time and log id.
   */
  protected function logRows(): array {

    if ($this->logRows !== NULL) {
      return $this->logRows;
    }

    $query = $this->database->select('log', 'l');
    $query->leftJoin('log__creatives', 'c', 'c.entity_id = l.id AND c.deleted = 0');
    $query->fields('l', ['id', 'type', 'created', 'project']);
    $query->addField('c', 'creatives_target_id', 'creative');
    $query->condition('l.type', [static::LOG_APPLY, static::LOG_MEDIATE], 'IN')
      ->orderBy('l.created')
      ->orderBy('l.id');

    $this->logRows = [];
    foreach ($query->execute() as $row) {
      $this->logRows[] = (object) [
        'id' => (int) $row->id,
        'type' => (string) $row->type,
        'created' => (int) $row->created,
        'project' => $row->project === NULL ? NULL : (int) $row->project,
        'creative' => $row->creative === NULL ? NULL : (int) $row->creative,
      ];
    }
    return $this->logRows;
  }

}
