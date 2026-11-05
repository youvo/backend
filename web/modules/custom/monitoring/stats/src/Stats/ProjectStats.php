<?php

namespace Drupal\stats\Stats;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\projects\ProjectState;
use Drupal\projects\ProjectTransition;
use Drupal\stats\StatsPeriod;
use Drupal\stats\StatsScope;

/**
 * Computes the project stats from the lifecycle history.
 *
 * The history of every project in the scope is read up to now, and each
 * metric applies its own window in PHP: the flow counts rows in the period,
 * while the cohort follows its projects beyond the end of the period.
 */
class ProjectStats {

  /**
   * The archive reason that removes a project from the mediation rate.
   */
  const string REASON_EXTERNAL = 'external';

  /**
   * The stage pairs of the medians, keyed by name.
   */
  const array STAGES = [
    'submit_publish' => ['submit', 'publish'],
    'publish_mediate' => ['publish', 'mediate'],
    'mediate_complete' => ['mediate', 'complete'],
  ];

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
  public function flow(StatsPeriod $period, StatsScope $scope): array {

    $empty = array_fill_keys(array_keys($period->buckets()), 0);
    $flow = [];
    foreach (ProjectTransition::cases() as $transition) {
      $flow[$transition->value] = $empty;
    }

    foreach ($this->history($scope) as $project) {
      foreach ($project['rows'] as [$transition, $timestamp]) {
        $bucket = $period->bucketOf($timestamp);
        if ($bucket !== NULL && isset($flow[$transition])) {
          $flow[$transition][$bucket]++;
        }
      }
    }

    return $flow;
  }

  /**
   * Computes the cohort of projects first published in the period.
   *
   * @return array{published: int, mediated: int, completed: int, excluded: int, mediation_rate: ?float, completion_rate: ?float, project_ids: int[]}
   *   The cohort counts and rates between 0 and 1.
   */
  public function cohort(StatsPeriod $period, StatsScope $scope): array {

    $cohort = [];
    foreach ($this->history($scope) as $id => $project) {
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
      'excluded' => $excluded,
      'mediation_rate' => $denominator > 0 ? (float) $mediated / $denominator : NULL,
      'completion_rate' => $mediated > 0 ? (float) $completed / $mediated : NULL,
      'project_ids' => array_keys($cohort),
    ];
  }

  /**
   * Computes the median days between two stages.
   *
   * For each row of the later stage in the period, the time since the most
   * recent preceding row of the earlier stage of the same project counts.
   *
   * @return array{submit_publish: ?float, publish_mediate: ?float, mediate_complete: ?float}
   *   The medians in days with one decimal, NULL without pairs.
   */
  public function stageMedians(StatsPeriod $period, StatsScope $scope): array {

    $durations = array_fill_keys(array_keys(static::STAGES), []);
    foreach ($this->history($scope) as $project) {
      $last = [];
      foreach ($project['rows'] as [$transition, $timestamp]) {
        foreach (static::STAGES as $stage => [$from, $to]) {
          if ($transition === $to && isset($last[$from]) && $period->contains($timestamp)) {
            $durations[$stage][] = $timestamp - $last[$from];
          }
        }
        $last[$transition] = $timestamp;
      }
    }

    $medians = [];
    foreach ($durations as $stage => $values) {
      $median = static::median($values);
      $medians[$stage] = $median === NULL ? NULL : round($median / 86400, 1);
    }
    return $medians;
  }

  /**
   * Gets the publish rows in the period.
   *
   * @return array<int, array{project: int, ts: int}>
   *   The publish rows ordered by time.
   */
  public function publishRows(StatsPeriod $period, StatsScope $scope): array {

    $rows = [];
    foreach ($this->history($scope) as $id => $project) {
      foreach ($project['rows'] as [$transition, $timestamp]) {
        if ($transition === ProjectTransition::Publish->value && $period->contains($timestamp)) {
          $rows[] = ['project' => $id, 'ts' => $timestamp];
        }
      }
    }
    usort($rows, fn($a, $b) => [$a['ts'], $a['project']] <=> [$b['ts'], $b['project']]);

    return $rows;
  }

  /**
   * Counts the projects ever mediated and completed by each bucket end.
   *
   * Rows before the period count too.
   *
   * @return array{mediated: array<string, int>, completed: array<string, int>}
   *   The running totals per bucket.
   */
  public function cumulative(StatsPeriod $period, StatsScope $scope): array {

    $first = [
      ProjectTransition::Mediate->value => [],
      ProjectTransition::Complete->value => [],
    ];
    foreach ($this->history($scope) as $id => $project) {
      foreach ($project['rows'] as [$transition, $timestamp]) {
        if (isset($first[$transition]) && !isset($first[$transition][$id])) {
          $first[$transition][$id] = $timestamp;
        }
      }
    }

    $totals = ['mediated' => [], 'completed' => []];
    foreach ($period->buckets() as $bucket => [, $end]) {
      $totals['mediated'][$bucket] = count(array_filter($first[ProjectTransition::Mediate->value], fn($timestamp) => $timestamp < $end));
      $totals['completed'][$bucket] = count(array_filter($first[ProjectTransition::Complete->value], fn($timestamp) => $timestamp < $end));
    }

    return $totals;
  }

  /**
   * Gets the lifecycle history of the projects in the scope.
   *
   * @return array<int, array{status: int, rows: array<int, array{0: string, 1: int}>}>
   *   The project status and the transitions with timestamps in history
   *   order, keyed by project.
   */
  protected function history(StatsScope $scope): array {

    $organization_ids = $scope->organizationIds();
    if ($organization_ids === []) {
      return [];
    }

    $query = $this->database->select('project__field_lifecycle_history', 'h');
    $query->innerJoin('project_field_data', 'p', 'p.id = h.entity_id AND p.langcode = h.langcode');
    $query->addField('h', 'entity_id');
    $query->addField('h', 'field_lifecycle_history_transition', 'transition');
    $query->addField('h', 'field_lifecycle_history_timestamp', 'timestamp');
    $query->addField('p', 'status');
    $query->condition('h.deleted', 0)
      ->isNotNull('h.field_lifecycle_history_transition')
      ->orderBy('h.entity_id')
      ->orderBy('h.delta');
    if ($organization_ids !== NULL) {
      $query->condition('p.uid', $organization_ids, 'IN');
    }

    $history = [];
    foreach ($query->execute() as $row) {
      $id = (int) $row->entity_id;
      $history[$id]['status'] = (int) $row->status;
      $history[$id]['rows'][] = [$row->transition, (int) $row->timestamp];
    }
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
  protected static function median(array $values): ?float {
    $count = count($values);
    if ($count === 0) {
      return NULL;
    }
    sort($values);
    $middle = intdiv($count, 2);
    return $count % 2 ? (float) $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
  }

}
