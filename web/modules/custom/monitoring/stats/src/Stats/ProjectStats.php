<?php

namespace Drupal\stats\Stats;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\stats\StatsPeriod;

/**
 * Computes the project stats from the lifecycle history.
 *
 * The history of every project is read up to now, and each
 * metric applies its own window in PHP: the flow counts rows in the period,
 * while the cohort follows its projects beyond the end of the period.
 */
class ProjectStats {

  /**
   * The archive reason that removes a project from the mediation rate.
   */
  const string REASON_EXTERNAL = 'external';

  /**
   * The log pattern of an application.
   */
  const string LOG_APPLY = 'project_apply';

  /**
   * The stage pairs of the medians, keyed by name.
   */
  const array STAGES = [
    'submit_publish' => ['submit', 'publish'],
    'publish_mediate' => ['publish', 'mediate'],
    'mediate_complete' => ['mediate', 'complete'],
  ];

  /**
   * The lifecycle history, once read in this request.
   *
   * Every metric of the page reads it. It has no time bound, so one read
   * serves all periods.
   *
   * @var array<int, array{status: int, rows: array<int, array{0: string, 1: int}>, states: array<int, array{0: ?string, 1: int}>}>|null
   */
  protected ?array $history = NULL;

  /**
   * Constructs a ProjectStats object.
   */
  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * Counts the transitions in the period per bucket.
   *
   * @return array<string, array<string, int>>
   *   The counts keyed by transition and bucket. Every bucket is present.
   */
  public function flow(StatsPeriod $period): array {

    $flow = [];
    foreach (ProjectTransition::cases() as $transition) {
      $flow[$transition->value] = $period->emptyBuckets();
    }

    foreach ($this->history() as $project) {
      foreach ($project['rows'] as [$transition, $timestamp]) {
        if (isset($flow[$transition]) && $period->contains($timestamp)) {
          $flow[$transition][$period->bucketOf($timestamp)]++;
        }
      }
    }

    return $flow;
  }

  /**
   * Computes the cohort of projects first published in the period.
   *
   * @return array{published: int, mediated: int, completed: int, mediation_rate: ?float}
   *   The cohort counts and the mediation rate between 0 and 1.
   */
  public function cohort(StatsPeriod $period): array {

    $cohort = [];
    foreach ($this->history() as $id => $project) {
      $published = FALSE;
      $mediated = FALSE;
      $completed = FALSE;
      foreach ($project['rows'] as [$transition, $timestamp]) {
        if ($transition === ProjectTransition::Publish->value && !$published) {
          if (!$period->contains($timestamp)) {
            continue 2;
          }
          $published = TRUE;
        }
        elseif ($transition === ProjectTransition::Mediate->value && $published) {
          $mediated = TRUE;
        }
        elseif ($transition === ProjectTransition::Complete->value) {
          $completed = TRUE;
        }
      }
      if ($published) {
        $cohort[$id] = [
          'mediated' => $mediated,
          'completed' => $completed,
          'status' => $project['status'],
        ];
      }
    }

    $states = $this->states(array_keys($cohort));
    $external = $this->externallyArchived(array_keys($cohort));
    $mediated = 0;
    $completed = 0;
    $excluded = 0;
    foreach ($cohort as $id => $project) {
      $mediated += (int) $project['mediated'];
      $completed += (int) $project['completed'];
      if ($project['mediated']) {
        continue;
      }
      $open = ($states[$id] ?? NULL) === ProjectState::Open->value && $project['status'] === 1;
      $archived_external = $project['status'] === 0 && isset($external[$id]);
      if ($open || $archived_external) {
        $excluded++;
      }
    }

    $published = count($cohort);
    $denominator = $published - $excluded;
    return [
      'published' => $published,
      'mediated' => $mediated,
      'completed' => $completed,
      'mediation_rate' => $denominator > 0 ? (float) $mediated / $denominator : NULL,
    ];
  }

  /**
   * Computes the median days between two stages.
   *
   * For each row of the later stage in the period, the time since the most
   * recent preceding row of the earlier stage of the same project counts. For
   * each publish row in the period, the time to the first application after
   * it counts.
   *
   * @return array{submit_publish: ?float, publish_application: ?float, publish_mediate: ?float, mediate_complete: ?float}
   *   The medians in days with one decimal, NULL without pairs.
   */
  public function stageMedians(StatsPeriod $period): array {

    $durations = array_fill_keys(['submit_publish', 'publish_application', 'publish_mediate', 'mediate_complete'], []);
    $publishes = [];
    foreach ($this->history() as $id => $project) {
      $last = [];
      foreach ($project['rows'] as [$transition, $timestamp]) {
        foreach (static::STAGES as $stage => [$from, $to]) {
          if ($transition === $to && isset($last[$from]) && $period->contains($timestamp)) {
            $durations[$stage][] = $timestamp - $last[$from];
          }
        }
        if ($transition === ProjectTransition::Publish->value && $period->contains($timestamp)) {
          $publishes[] = [$id, $timestamp];
        }
        $last[$transition] = $timestamp;
      }
    }
    $durations['publish_application'] = $this->firstApplicationDurations($period, $publishes);

    $medians = [];
    foreach ($durations as $stage => $values) {
      $median = static::median($values);
      $medians[$stage] = $median === NULL ? NULL : round($median / 86400, 1);
    }
    return $medians;
  }

  /**
   * Gets the seconds from each publish to the first application after it.
   *
   * @param \Drupal\stats\StatsPeriod $period
   *   The period of the publish rows.
   * @param array<int, array{0: int, 1: int}> $publishes
   *   The publish rows as project and timestamp.
   *
   * @return int[]
   *   The durations of the publish rows with an application.
   */
  protected function firstApplicationDurations(StatsPeriod $period, array $publishes): array {

    if (!$publishes) {
      return [];
    }

    $query = $this->database->select('log', 'l')
      ->fields('l', ['project', 'created'])
      ->condition('l.type', static::LOG_APPLY)
      ->condition('l.project', array_unique(array_column($publishes, 0)), 'IN')
      ->condition('l.created', $period->from()->getTimestamp(), '>')
      ->orderBy('l.created')
      ->orderBy('l.id');
    $applications = [];
    foreach ($query->execute() as $row) {
      $applications[(int) $row->project][] = (int) $row->created;
    }

    $durations = [];
    foreach ($publishes as [$project, $published]) {
      foreach ($applications[$project] ?? [] as $created) {
        if ($created > $published) {
          $durations[] = $created - $published;
          break;
        }
      }
    }
    return $durations;
  }

  /**
   * Counts the projects per lifecycle state at each bucket end.
   *
   * A project is in the state its last history item before the bucket end
   * went to, by timestamp and then by delta. Archived projects count too.
   * The current bucket counts the current states, the buckets starting after
   * the current time are NULL.
   *
   * @param \Drupal\stats\StatsPeriod $period
   *   The period.
   * @param int $now
   *   The current time.
   *
   * @return array{open: array<string, ?int>, ongoing: array<string, ?int>, completed: array<string, ?int>, mediated: array<string, ?int>}
   *   The counts per bucket, mediated being ongoing plus completed.
   */
  public function statesAtBucketEnds(StatsPeriod $period, int $now): array {

    $buckets = $period->buckets();
    $states = [
      ProjectState::Open->value => $period->emptyBuckets(),
      ProjectState::Ongoing->value => $period->emptyBuckets(),
      ProjectState::Completed->value => $period->emptyBuckets(),
    ];

    // The buckets that have ended, in their order.
    $ends = [];
    foreach ($buckets as $bucket => [, $end]) {
      if ($end <= $now) {
        $ends[$bucket] = $end;
      }
    }

    foreach ($this->history() as $project) {
      // The sort is stable, so items of one timestamp keep their delta order.
      $items = $project['states'];
      usort($items, fn($a, $b) => $a[1] <=> $b[1]);
      $index = 0;
      $count = count($items);
      $state = NULL;
      foreach ($ends as $bucket => $end) {
        while ($index < $count && $items[$index][1] < $end) {
          $state = $items[$index][0];
          $index++;
        }
        if ($state !== NULL && isset($states[$state])) {
          $states[$state][$bucket]++;
        }
      }
    }

    if (count($ends) < count($buckets)) {
      $current = $this->currentStateCounts();
      foreach (array_diff_key($buckets, $ends) as $bucket => [$start]) {
        foreach (array_keys($states) as $state) {
          $states[$state][$bucket] = $start <= $now ? $current[$state] ?? 0 : NULL;
        }
      }
    }

    $mediated = [];
    foreach ($states[ProjectState::Ongoing->value] as $bucket => $ongoing) {
      $mediated[$bucket] = $ongoing === NULL ? NULL : $ongoing + $states[ProjectState::Completed->value][$bucket];
    }

    return [
      'open' => $states[ProjectState::Open->value],
      'ongoing' => $states[ProjectState::Ongoing->value],
      'completed' => $states[ProjectState::Completed->value],
      'mediated' => $mediated,
    ];
  }

  /**
   * Counts the projects per current lifecycle state.
   *
   * It counts as StatsCalculator does.
   *
   * @return array<string, int>
   *   The counts keyed by state.
   */
  protected function currentStateCounts(): array {
    $query = $this->database->select('project', 'p');
    $query->join('project__field_lifecycle', 'l', 'p.id = l.entity_id');
    $query->addField('l', 'field_lifecycle_value', 'state');
    $query->addExpression('COUNT(*)', 'count');
    $query->groupBy('l.field_lifecycle_value');
    $counts = [];
    foreach ($query->execute() as $row) {
      $counts[(string) $row->state] = (int) $row->count;
    }
    return $counts;
  }

  /**
   * Gets the lifecycle history of all projects.
   *
   * @return array<int, array{status: int, rows: array<int, array{0: string, 1: int}>, states: array<int, array{0: ?string, 1: int}>}>
   *   The project status, the transitions with timestamps and the states the
   *   items went to with timestamps, both in history order, keyed by project.
   *   The states include the first item without a transition.
   */
  protected function history(): array {

    if ($this->history !== NULL) {
      return $this->history;
    }

    $query = $this->database->select('project__field_lifecycle_history', 'h');
    $query->innerJoin('project_field_data', 'p', 'p.id = h.entity_id AND p.langcode = h.langcode AND p.default_langcode = 1');
    $query->addField('h', 'entity_id');
    $query->addField('h', 'field_lifecycle_history_transition', 'transition');
    $query->addField('h', 'field_lifecycle_history_timestamp', 'timestamp');
    $query->addField('h', 'field_lifecycle_history_to', 'to_state');
    $query->addField('p', 'status');
    $query->condition('h.deleted', 0)
      ->orderBy('h.entity_id')
      ->orderBy('h.delta');

    $history = [];
    foreach ($query->execute() as $row) {
      $id = (int) $row->entity_id;
      $history[$id]['status'] = (int) $row->status;
      $history[$id]['rows'] ??= [];
      if ($row->transition !== NULL) {
        $history[$id]['rows'][] = [$row->transition, (int) $row->timestamp];
      }
      $history[$id]['states'][] = [$row->to_state, (int) $row->timestamp];
    }
    $this->history = $history;
    return $history;
  }

  /**
   * Gets the current lifecycle states of projects.
   *
   * @param int[] $project_ids
   *   The projects.
   *
   * @return array<int, string>
   *   The states keyed by project.
   */
  protected function states(array $project_ids): array {
    if (!$project_ids) {
      return [];
    }
    $states = $this->database->select('project__field_lifecycle', 'l')
      ->fields('l', ['entity_id', 'field_lifecycle_value'])
      ->condition('l.entity_id', $project_ids, 'IN')
      ->condition('l.deleted', 0)
      ->execute()
      ->fetchAllKeyed();
    $result = [];
    foreach ($states as $id => $state) {
      $result[(int) $id] = (string) $state;
    }
    return $result;
  }

  /**
   * Gets the projects whose latest archive log has the reason external.
   *
   * Archived logs count too, so the log status is not filtered.
   *
   * @param int[] $project_ids
   *   The projects.
   *
   * @return array<int, true>
   *   The externally archived projects as keys.
   */
  protected function externallyArchived(array $project_ids): array {

    if (!$project_ids) {
      return [];
    }

    $logs = $this->database->select('log', 'l')
      ->fields('l', ['project', 'type', 'misc'])
      ->condition('l.project', $project_ids, 'IN')
      ->condition('l.type', ['project_archive', 'project_unarchive'], 'IN')
      ->orderBy('l.created')
      ->orderBy('l.id')
      ->execute();
    $latest = [];
    foreach ($logs as $log) {
      $latest[(int) $log->project] = $log;
    }

    $external = [];
    foreach ($latest as $id => $log) {
      $misc = Json::decode($log->misc ?? '') ?? [];
      if ($log->type === 'project_archive' && ($misc['reason'] ?? NULL) === static::REASON_EXTERNAL) {
        $external[$id] = TRUE;
      }
    }
    return $external;
  }

  /**
   * Gets the median of numbers, or NULL for none.
   *
   * @param array<int, int|float> $values
   *   The numbers.
   */
  public static function median(array $values): ?float {
    $count = count($values);
    if ($count === 0) {
      return NULL;
    }
    sort($values);
    $middle = intdiv($count, 2);
    return $count % 2 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
  }

}
