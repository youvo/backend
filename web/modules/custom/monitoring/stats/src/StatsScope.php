<?php

namespace Drupal\stats;

use Drupal\Core\Database\Connection;

/**
 * Provides the organizations the stats are scoped to.
 *
 * Without a manager, the scope covers all organizations. With a manager, it
 * covers the organizations whose field_manager is that manager.
 */
class StatsScope {

  /**
   * The role a user needs to scope the stats.
   */
  const string MANAGER_ROLE = 'manager';

  /**
   * Constructs a StatsScope.
   *
   * @param int|null $managerId
   *   The manager, or NULL for all organizations.
   * @param int[]|null $organizationIds
   *   The organizations of the manager, or NULL for all organizations.
   */
  public function __construct(
    protected ?int $managerId = NULL,
    protected ?array $organizationIds = NULL,
  ) {}

  /**
   * Creates the scope from the manager query argument.
   *
   * A manager argument that is not the uid of a user with the manager role is
   * ignored and yields all organizations.
   */
  public static function fromQuery(array $query, Connection $database): static {

    $manager = $query['manager'] ?? NULL;
    if (!is_string($manager) || !preg_match('/^[1-9]\d{0,9}$/', $manager)) {
      return new static();
    }

    $is_manager = (bool) $database->select('user__roles', 'r')
      ->condition('r.entity_id', (int) $manager)
      ->condition('r.roles_target_id', static::MANAGER_ROLE)
      ->condition('r.deleted', 0)
      ->countQuery()
      ->execute()
      ->fetchField();
    if (!$is_manager) {
      return new static();
    }

    $organization_ids = $database->select('user__field_manager', 'm')
      ->fields('m', ['entity_id'])
      ->condition('m.field_manager_target_id', (int) $manager)
      ->condition('m.deleted', 0)
      ->orderBy('m.entity_id')
      ->execute()
      ->fetchCol();

    return new static((int) $manager, array_map('intval', $organization_ids));
  }

  /**
   * Gets the manager, or NULL for all organizations.
   */
  public function managerId(): ?int {
    return $this->managerId;
  }

  /**
   * Gets the organizations, or NULL for all organizations.
   *
   * @return int[]|null
   *   The organization ids, empty if the manager has none.
   */
  public function organizationIds(): ?array {
    return $this->organizationIds;
  }

}
