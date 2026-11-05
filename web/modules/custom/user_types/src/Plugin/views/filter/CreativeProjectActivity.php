<?php

namespace Drupal\user_types\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\BooleanOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters the creatives by whether a project lists them in a user field.
 *
 * The filter asks for the existence of a project that references the creative
 * in the configured project field. It joins nothing, so a creative who is
 * listed in several projects still appears once.
 */
#[ViewsFilter('creative_project_activity')]
class CreativeProjectActivity extends BooleanOperator {

  /**
   * Constructs the filter.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Creates the filter with its services.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Adds the existence condition for a project that lists the creative.
   */
  public function query(): void {
    $project_field = $this->definition['project_field'];
    $table_mapping = $this->entityTypeManager->getStorage('project')->getTableMapping();
    $table = $table_mapping->getFieldTableName($project_field);
    $column = $table_mapping->getColumnNames($project_field)['target_id'];

    $subquery = $this->database->select($table, 'project_activity');
    $subquery->addExpression('1');
    $subquery->where('[project_activity].[' . $column . '] = [users_field_data].[uid]');

    $condition = $this->database->condition('AND');
    if (empty($this->value)) {
      $condition->notExists($subquery);
    }
    else {
      $condition->exists($subquery);
    }
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $condition);
  }

}
