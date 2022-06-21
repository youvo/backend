<?php

namespace Drupal\stats;

use Drupal\Core\Database\Connection;

/**
 * Provides the stat calculator service.
 */
class StatsCalculator {

  /**
   * Constructs a StatsCalculator service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(protected Connection $database) {}

  /**
   * Counts creatives.
   */
  public function countCreatives(): int {
    $query = $this->database->select('users', 'u')->condition('u.type', 'user');
    $query->join('users_field_data', 'd', 'u.uid = d.uid');
    $query->condition('d.access', '0', '>');
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts organizations.
   */
  public function countOrganizations(): int {
    $query = $this->database->select('users', 'u')->condition('u.type', 'organization');
    $query->join('user__roles', 'r', 'u.uid = r.entity_id');
    $query->condition('r.roles_target_id', 'organization');
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
   */
  public function countOpenProjects(): int {
    $query = $this->database->select('project', 'p');
    $query->join('project__field_lifecycle', 'l', 'p.id = l.entity_id');
    $query->condition('l.field_lifecycle_value', 'open');
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts ongoing projects.
   */
  public function countOngoingProjects(): int {
    $query = $this->database->select('project', 'p');
    $query->join('project__field_lifecycle', 'l', 'p.id = l.entity_id');
    $query->condition('l.field_lifecycle_value', 'ongoing');
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts completed projects.
   */
  public function countCompletedProjects(): int {
    $query = $this->database->select('project', 'p');
    $query->join('project__field_lifecycle', 'l', 'p.id = l.entity_id');
    $query->condition('l.field_lifecycle_value', 'completed');
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Counts mediated projects.
   */
  public function countMediatedProjects(): int {
    $query = $this->database->select('project', 'p');
    $query->join('project__field_lifecycle', 'l', 'p.id = l.entity_id');
    $query->condition('l.field_lifecycle_value', ['ongoing', 'completed'], 'IN');
    return $query->countQuery()->execute()->fetchField();
  }

}
