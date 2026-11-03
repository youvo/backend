<?php

namespace Drupal\logbook\Plugin\views\argument;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\logbook\Plugin\views\LogMatchTrait;
use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\NumericArgument;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Limits the logs to those of a person or an organization.
 *
 * Matches the same logs as a tag of the user in the logbook search: the logs
 * they wrote, take part in as creative, manage or belong to, and the logs of
 * the projects they own.
 */
#[ViewsArgument('logbook_involves')]
class LogbookInvolves extends NumericArgument {

  use LogMatchTrait;

  /**
   * Constructs the contextual filter.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
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
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('database'),
    );
  }

  /**
   * Adds the condition on the people and organizations of the log.
   *
   * @param bool $group_by
   *   Whether the query uses aggregation, which this argument ignores.
   */
  public function query($group_by = FALSE): void {
    $alias = $this->ensureMyTable();
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere('0', $this->entityCondition($alias, [(int) $this->argument], []));
  }

}
