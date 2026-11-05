<?php

namespace Drupal\stats;

use Drupal\Core\Database\Connection;

/**
 * Provides the stat calculator service.
 */
class StatsCalculator {

  /**
   * Constructs a StatsCalculator service.
   */
  public function __construct(protected Connection $database) {}

  /**
   * Counts creatives.
   *
   * @param int|null $until
   *   Counts only creatives registered before this timestamp, if given.
   */
  public function countCreatives(?int $until = NULL): int {
    $query = $this->database->select('users', 'u')->condition('u.type', 'user');
    $query->join('users_field_data', 'd', 'u.uid = d.uid');
    $query->condition('d.access', '0', '>');
    if ($until !== NULL) {
      $query->condition('d.created', $until, '<');
    }
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts organizations.
   *
   * @param int|null $until
   *   Counts only organizations registered before this timestamp, if given.
   */
  public function countOrganizations(?int $until = NULL): int {
    $query = $this->database->select('users', 'u')->condition('u.type', 'organization');
    $query->join('user__roles', 'r', 'u.uid = r.entity_id');
    $query->condition('r.roles_target_id', 'organization');
    if ($until !== NULL) {
      $query->join('users_field_data', 'd', 'u.uid = d.uid');
      $query->condition('d.created', $until, '<');
    }
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts proposals.
   */
  public function countProposals(): int {
    // Assume that every organization has made a proposal.
    return $this->database->select('users', 'u')
      ->condition('u.type', 'organization')
      ->countQuery()->execute()->fetchField();
  }

  /**
   * Counts pending proposals.
   */
  public function countManagedProposals(): int {
    $query = $this->database->select('users', 'u')
      ->condition('u.type', 'organization');
    $query->join('user__roles', 'r', 'u.uid = r.entity_id');
    $query->condition('r.roles_target_id', 'prospect');
    $query->join('user__field_manager', 'm', 'u.uid = m.entity_id');
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts unmanaged proposals.
   */
  public function countUnmanagedProposals(): int {
    $query = $this->database->select('users', 'u')
      ->condition('u.type', 'organization');
    $query->join('user__roles', 'r', 'u.uid = r.entity_id');
    $query->condition('r.roles_target_id', 'prospect');
    $query->leftJoin('user__field_manager', 'm', 'u.uid = m.entity_id');
    $query->isNull('m.field_manager_target_id');
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts open projects.
   *
   * @param int|null $until
   *   Counts the projects that were open at this timestamp, if given.
   */
  public function countOpenProjects(?int $until = NULL): int {
    return $this->countProjects(['open'], $until);
  }

  /**
   * Counts ongoing projects.
   *
   * @param int|null $until
   *   Counts the projects that were ongoing at this timestamp, if given.
   */
  public function countOngoingProjects(?int $until = NULL): int {
    return $this->countProjects(['ongoing'], $until);
  }

  /**
   * Counts completed projects.
   *
   * @param int|null $until
   *   Counts the projects that were completed at this timestamp, if given.
   */
  public function countCompletedProjects(?int $until = NULL): int {
    return $this->countProjects(['completed'], $until);
  }

  /**
   * Counts mediated projects, which are ongoing or completed.
   *
   * @param int|null $until
   *   Counts the projects that were mediated at this timestamp, if given.
   */
  public function countMediatedProjects(?int $until = NULL): int {
    return $this->countProjects(['ongoing', 'completed'], $until);
  }

  /**
   * Counts projects in one of the lifecycle states.
   *
   * @param string[] $states
   *   The lifecycle states.
   * @param int|null $until
   *   The timestamp. The state then is the target of the last lifecycle history
   *   item before it. Without it, the current state counts.
   */
  protected function countProjects(array $states, ?int $until): int {

    if ($until === NULL) {
      $query = $this->database->select('project', 'p');
      $query->join('project__field_lifecycle', 'l', 'p.id = l.entity_id');
      $query->condition('l.field_lifecycle_value', $states, 'IN');
      return $query->countQuery()->execute()->fetchField();
    }

    $query = $this->database->select('project__field_lifecycle_history', 'h');
    $query->innerJoin('project_field_data', 'p', 'p.id = h.entity_id AND p.langcode = h.langcode AND p.default_langcode = 1');
    $query->condition('h.deleted', 0)
      ->condition('h.field_lifecycle_history_timestamp', $until, '<')
      ->condition('h.field_lifecycle_history_to', $states, 'IN');

    // The item is the last one before $until, no later item of the project
    // exists, by timestamp and then by delta.
    $later = $this->database->select('project__field_lifecycle_history', 'n');
    $later->addExpression('1');
    $later->where('n.entity_id = h.entity_id AND n.langcode = h.langcode');
    $later->condition('n.deleted', 0)
      ->condition('n.field_lifecycle_history_timestamp', $until, '<');
    $later->where('n.field_lifecycle_history_timestamp > h.field_lifecycle_history_timestamp OR (n.field_lifecycle_history_timestamp = h.field_lifecycle_history_timestamp AND n.delta > h.delta)');
    $query->notExists($later);

    return $query->countQuery()->execute()->fetchField();
  }

}
