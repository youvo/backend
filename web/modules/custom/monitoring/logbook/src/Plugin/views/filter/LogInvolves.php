<?php

namespace Drupal\logbook\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\logbook\SearchTags;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters the logs by the people, organizations, projects and texts in them.
 *
 * The value is a list of SearchTags tokens. A person or an organization
 * matches the logs they wrote, take part in as creative, manage or belong to,
 * and the logs of the projects they own. Tags of people, organizations and
 * projects add up. Each text narrows the result: it is looked for in the
 * message, the project title and the organization name.
 *
 * Multi-value fields are matched in subqueries, so a log is never listed
 * twice.
 */
#[ViewsFilter('log_involves')]
class LogInvolves extends FilterPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs the filter.
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
    // The filter has no operator to choose.
    $this->no_operator = TRUE;
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
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['value']['default'] = [];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $form['value'] = [
      '#type' => 'logbook_search_tags',
      '#placeholder' => 'Person, Organisation, Projekt oder Text …',
      '#default_value' => SearchTags::fromInput($this->value),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }
    $this->value = SearchTags::fromInput($input[$this->options['expose']['identifier']] ?? NULL);
    return $this->value !== [];
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $tags = SearchTags::parse(SearchTags::fromInput($this->value));
    if (!$tags['user'] && !$tags['project'] && !$tags['text']) {
      return;
    }
    $alias = $this->ensureMyTable();
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    if ($tags['user'] || $tags['project']) {
      $or = new Condition('OR');
      if ($users = $tags['user']) {
        foreach (['author', 'manager', 'organization', 'creatives'] as $field) {
          $this->logCondition($or, $alias, $field, $users, 'IN');
        }
        $this->logCondition($or, $alias, 'project', $this->ids('project', 'uid', $users, 'IN'), 'IN');
      }
      if ($projects = $tags['project']) {
        $this->logCondition($or, $alias, 'project', $projects, 'IN');
      }
      $query->addWhere($this->options['group'], $or);
    }

    foreach ($tags['text'] as $text) {
      $like = '%' . $this->database->escapeLike($text) . '%';
      $or = new Condition('OR');
      $this->logCondition($or, $alias, 'message', $like, 'LIKE');
      $this->logCondition($or, $alias, 'project', $this->ids('project', 'title', $like, 'LIKE'), 'IN');
      $this->logCondition($or, $alias, 'organization', $this->ids('user', 'field_name', $like, 'LIKE'), 'IN');
      $query->addWhere($this->options['group'], $or);
    }
  }

  /**
   * Adds a condition on a field of the log.
   *
   * A field in the base table is matched there, any other in a subquery.
   */
  protected function logCondition(Condition $condition, string $alias, string $field, mixed $value, string $operator): void {
    [$table, $column] = $this->column('log', $field);
    if ($table === $this->table) {
      $condition->condition("$alias.$column", $value, $operator);
      return;
    }
    $condition->condition("$alias.id", $this->ids('log', $field, $value, $operator), 'IN');
  }

  /**
   * Selects the ids of the entities whose field matches.
   */
  protected function ids(string $entity_type_id, string $field, mixed $value, string $operator): SelectInterface {
    [$table, $column, $id] = $this->column($entity_type_id, $field);
    return $this->database->select($table, 't')
      ->fields('t', [$id])
      ->condition("t.$column", $value, $operator);
  }

  /**
   * Gets where a field of an entity type is stored.
   *
   * @return string[]
   *   The table, the column of the main property and the column of the
   *   entity id in that table.
   */
  protected function column(string $entity_type_id, string $field): array {
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    assert($storage instanceof SqlEntityStorageInterface);
    $mapping = $storage->getTableMapping();
    assert($mapping instanceof DefaultTableMapping);
    $definition = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id)[$field];
    $id = $mapping->requiresDedicatedTableStorage($definition)
      ? 'entity_id'
      : (string) $storage->getEntityType()->getKey('id');
    return [
      $mapping->getFieldTableName($field),
      $mapping->getFieldColumnName($definition, (string) $definition->getMainPropertyName()),
      $id,
    ];
  }

}
