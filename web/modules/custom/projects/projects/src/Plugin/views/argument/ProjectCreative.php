<?php

namespace Drupal\projects\Plugin\views\argument;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Limits the projects to those of a creative.
 *
 * A creative's projects are the ones they take part in and the ones of the
 * organizations they manage.
 */
#[ViewsArgument('project_creative')]
class ProjectCreative extends NumericArgument {

  /**
   * Constructs the contextual filter.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
    );
  }

  /**
   * Adds the condition on the participants and the managed organizations.
   *
   * @param bool $group_by
   *   Whether the query uses aggregation, which this argument ignores.
   */
  public function query($group_by = FALSE): void {
    $alias = $this->ensureMyTable();
    $creative = (int) $this->argument;

    $participations = $this->database->select('project__field_participants', 'p')
      ->fields('p', ['entity_id'])
      ->condition('p.field_participants_target_id', $creative);
    $organizations = $this->database->select('user__field_manager', 'm')
      ->fields('m', ['entity_id'])
      ->condition('m.field_manager_target_id', $creative);

    $condition = (new Condition('OR'))
      ->condition("$alias.id", $participations, 'IN')
      ->condition("$alias.uid", $organizations, 'IN');
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere('0', $condition);
  }

}
