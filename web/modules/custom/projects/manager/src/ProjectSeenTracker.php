<?php

namespace Drupal\manager;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\logbook\LogInterface;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\user_types\Utility\Profile;

/**
 * Tracks which logs of their projects the managers have seen.
 *
 * A manager confirms a project. The row of the manager and the project holds
 * the highest log id of that moment: the logs above it are new. A project
 * without a row is new as a whole.
 */
class ProjectSeenTracker {

  /**
   * The table of the confirmations.
   */
  public const TABLE = 'manager_project_seen';

  /**
   * The lifecycle states of the projects a manager follows.
   */
  public const UNFINISHED = ['draft', 'pending', 'open', 'ongoing'];

  /**
   * The number of latest logs that the activity returns per project.
   */
  public const LATEST = 3;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected TimeInterface $time,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Gets the cache tag of the confirmations of a user.
   */
  public static function cacheTag(int|string $uid): string {
    return 'manager_project_seen:' . $uid;
  }

  /**
   * Gets the unfinished projects of the organizations a user manages.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param int $limit
   *   The number of projects to return at most, 0 for all. Only the returned
   *   projects are loaded.
   *
   * @return array<int, \Drupal\projects\ProjectInterface>
   *   The projects keyed by id, the one with the newest activity first.
   */
  public function getManagedProjects(AccountInterface $account, int $limit = 0): array {
    $organization_ids = $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_manager', (int) Profile::id($account))
      ->execute();
    if (!$organization_ids) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('project');
    $project_ids = array_map('intval', array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', array_values($organization_ids), 'IN')
      ->condition('field_lifecycle', self::UNFINISHED, 'IN')
      ->execute()));
    if (!$project_ids) {
      return [];
    }
    $last = $this->lastLogIds($project_ids);
    usort($project_ids, static fn (int $a, int $b) => (($last[$b] ?? 0) <=> ($last[$a] ?? 0)) ?: $b <=> $a);
    if ($limit > 0) {
      $project_ids = array_slice($project_ids, 0, $limit);
    }
    $loaded = $storage->loadMultiple($project_ids);
    $projects = [];
    foreach ($project_ids as $id) {
      if (isset($loaded[$id])) {
        $projects[$id] = $loaded[$id];
      }
    }
    return $projects;
  }

  /**
   * Gets the new activity of projects for a user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param \Drupal\projects\ProjectInterface[] $projects
   *   The projects.
   *
   * @return array<int, array{is_new: bool, count: int, latest: \Drupal\logbook\LogInterface[]}>
   *   Per project id: whether it is new, the number of new logs and the latest
   *   observable logs of the project (including the user's own).
   */
  public function getActivity(AccountInterface $account, array $projects): array {
    $uid = (int) Profile::id($account);
    $ids = array_map(static fn (ProjectInterface $p) => (int) $p->id(), array_values($projects));
    if (!$ids) {
      return [];
    }
    $seen = $this->loadSeen($uid, $ids);
    $logs = $this->observableLogs($ids);
    $latest_ids = [];
    $activity = [];
    foreach ($ids as $id) {
      $of_project = $logs[$id] ?? [];
      $by_others = array_filter($of_project, static fn (array $log) => $log['author'] !== $uid);
      $new = isset($seen[$id])
        ? array_filter($by_others, static fn (array $log) => $log['id'] > $seen[$id])
        : $by_others;
      $latest = array_slice(array_column($of_project, 'id'), 0, self::LATEST);
      $latest_ids = array_merge($latest_ids, $latest);
      $activity[$id] = [
        'is_new' => isset($seen[$id]) ? $new !== [] : TRUE,
        'count' => count($new),
        'latest' => $latest,
      ];
    }
    $loaded = $latest_ids ? $this->entityTypeManager->getStorage('log')->loadMultiple($latest_ids) : [];
    foreach ($activity as &$item) {
      $item['latest'] = array_values(array_filter(
        array_map(static fn (int $log_id) => $loaded[$log_id] ?? NULL, $item['latest']),
        static fn ($log) => $log instanceof LogInterface,
      ));
    }
    return $activity;
  }

  /**
   * Checks if a user manages the organization of a project.
   */
  public function isManagedBy(AccountInterface $account, ProjectInterface $project): bool {
    try {
      $organization = $project->getOwner();
    }
    catch (\LogicException) {
      return FALSE;
    }
    return $organization instanceof Organization && $organization->isManager($account);
  }

  /**
   * Confirms a project for a user.
   *
   * @return bool
   *   FALSE if the user does not manage the project. Nothing is written then.
   */
  public function markSeen(AccountInterface $account, ProjectInterface $project): bool {
    if (!$this->isManagedBy($account, $project)) {
      return FALSE;
    }
    $uid = (int) Profile::id($account);
    $this->database->merge(self::TABLE)
      ->keys(['uid' => $uid, 'project_id' => (int) $project->id()])
      ->fields(['log_id' => $this->maxLogId(), 'confirmed' => $this->time->getRequestTime()])
      ->execute();
    $this->cacheTagsInvalidator->invalidateTags([self::cacheTag($uid)]);
    return TRUE;
  }

  /**
   * Confirms all the unfinished projects a user manages.
   */
  public function markAllSeen(AccountInterface $account): void {
    foreach ($this->getManagedProjects($account) as $project) {
      $this->markSeen($account, $project);
    }
  }

  /**
   * Deletes the confirmations of a project.
   */
  public function deleteForProject(int|string $project_id): void {
    $this->database->delete(self::TABLE)->condition('project_id', $project_id)->execute();
  }

  /**
   * Deletes the confirmations of a user.
   */
  public function deleteForUser(int|string $uid): void {
    $this->database->delete(self::TABLE)->condition('uid', $uid)->execute();
    $this->cacheTagsInvalidator->invalidateTags([self::cacheTag($uid)]);
  }

  /**
   * Gets the highest log id.
   */
  protected function maxLogId(): int {
    return (int) $this->database->query('SELECT MAX([id]) FROM {log}')->fetchField();
  }

  /**
   * Loads the last seen log ids of a user for projects.
   *
   * @param int $uid
   *   The user.
   * @param int[] $project_ids
   *   The projects.
   *
   * @return array<int, int>
   *   The log ids keyed by project id.
   */
  protected function loadSeen(int $uid, array $project_ids): array {
    $rows = $this->database->select(self::TABLE, 's')
      ->fields('s', ['project_id', 'log_id'])
      ->condition('uid', $uid)
      ->condition('project_id', $project_ids, 'IN')
      ->execute()
      ->fetchAllKeyed();
    $seen = [];
    foreach ($rows as $project_id => $log_id) {
      $seen[(int) $project_id] = (int) $log_id;
    }
    return $seen;
  }

  /**
   * Gets the observable logs of projects, newest first.
   *
   * The pattern flags decide, as in the logbook: enabled and observable.
   *
   * @param int[] $project_ids
   *   The projects.
   *
   * @return array<int, array<int, array{id: int, author: int}>>
   *   The logs per project id.
   */
  protected function observableLogs(array $project_ids): array {
    $pattern_ids = $this->observablePatternIds();
    if (!$pattern_ids) {
      return [];
    }
    $rows = $this->database->select('log', 'l')
      ->fields('l', ['id', 'project', 'author'])
      ->condition('l.project', $project_ids, 'IN')
      ->condition('l.type', $pattern_ids, 'IN')
      ->orderBy('l.id', 'DESC')
      ->execute();
    $logs = [];
    foreach ($rows as $row) {
      $logs[(int) $row->project][] = ['id' => (int) $row->id, 'author' => (int) $row->author];
    }
    return $logs;
  }

  /**
   * Gets the ids of the enabled, observable log patterns.
   *
   * The pattern flags decide, as in the logbook.
   *
   * @return string[]
   *   The pattern ids.
   */
  protected function observablePatternIds(): array {
    return array_map('strval', array_keys($this->entityTypeManager->getStorage('log_pattern')
      ->loadByProperties(['status' => TRUE, 'observable' => TRUE])));
  }

  /**
   * Gets the highest observable log id per project.
   *
   * @param int[] $project_ids
   *   The projects.
   *
   * @return array<int, int>
   *   The log ids keyed by project id.
   */
  protected function lastLogIds(array $project_ids): array {
    $pattern_ids = $this->observablePatternIds();
    if (!$pattern_ids) {
      return [];
    }
    $query = $this->database->select('log', 'l')
      ->fields('l', ['project'])
      ->condition('l.project', $project_ids, 'IN')
      ->condition('l.type', $pattern_ids, 'IN')
      ->groupBy('l.project');
    $query->addExpression('MAX([l].[id])', 'last_id');
    $last = [];
    foreach ($query->execute() as $row) {
      $last[(int) $row->project] = (int) $row->last_id;
    }
    return $last;
  }

}
