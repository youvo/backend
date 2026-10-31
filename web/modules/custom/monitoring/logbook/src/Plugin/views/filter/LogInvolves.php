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
use Drupal\logbook\Plugin\Field\ComputedTextFieldItemListBase;
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
 * message, the log text of the pattern, the project title and the names of
 * the people and organizations in the log.
 *
 * Multi-value fields are matched in subqueries, so a log is never listed
 * twice.
 */
#[ViewsFilter('log_involves')]
class LogInvolves extends FilterPluginBase {

  /**
   * The fields of the log that hold the people and organizations in it.
   */
  protected const array PARTICIPANTS = ['author', 'manager', 'organization', 'creatives'];

  /**
   * The words of the log texts by pattern id, without their placeholders.
   *
   * @var array<string, string[]>|null
   */
  protected ?array $logTextWords = NULL;

  /**
   * The ids of the patterns whose log text shows the role of the author.
   *
   * @var string[]|null
   */
  protected ?array $authorRolePatterns = NULL;

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

    $tags = SearchTags::parse($this->value);
    if (!$tags['user'] && !$tags['project'] && !$tags['text']) {
      return;
    }
    $alias = $this->ensureMyTable();
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;

    if ($tags['user'] || $tags['project']) {
      $or = new Condition('OR');
      if ($users = $tags['user']) {
        foreach (self::PARTICIPANTS as $field) {
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
      // The names of the people and organizations in the log, in every role,
      // as a tag of them would find it.
      $names = $this->ids('user', 'field_name', $like, 'LIKE');
      foreach (self::PARTICIPANTS as $field) {
        $this->logCondition($or, $alias, $field, clone $names, 'IN');
      }
      // As an organization tag, the name finds the logs of its projects.
      $this->logCondition($or, $alias, 'project', $this->ids('project', 'uid', $names, 'IN'), 'IN');
      if ($patterns = $this->patternsByText($text)) {
        $this->logCondition($or, $alias, 'type', $patterns, 'IN');
      }
      $this->addAuthorRoleConditions($or, $alias, $text);
      $query->addWhere($this->options['group'], $or);
    }
  }

  /**
   * Gets the patterns whose log text contains the text.
   *
   * The log text is what the logbook shows around the names, so it is
   * matched without its placeholders: "%Creative" is no word of the text.
   *
   * @return string[]
   *   The pattern ids.
   */
  protected function patternsByText(string $text): array {
    $this->loadLogTexts();
    $matches = array_filter(
      $this->logTextWords,
      static fn (array $texts) => array_filter($texts, static fn (string $words) => mb_stripos($words, $text) !== FALSE) !== [],
    );
    return array_map('strval', array_keys($matches));
  }

  /**
   * Reads the log texts once for all texts of the search.
   */
  protected function loadLogTexts(): void {
    if ($this->logTextWords !== NULL) {
      return;
    }
    $this->logTextWords = [];
    $this->authorRolePatterns = [];
    /** @var \Drupal\logbook\LogTextInterface $log_text */
    foreach ($this->entityTypeManager->getStorage('log_text')->loadMultiple() as $log_text) {
      $pattern = (string) $log_text->get('log_pattern')->target_id;
      if ($pattern === '') {
        continue;
      }
      $text = $log_text->getText();
      $words = preg_replace('/%\w+/u', ' ', $text) ?? '';
      $this->logTextWords[$pattern][] = $words;
      if (str_contains($text, '%AuthorWithRole')) {
        $this->authorRolePatterns[$pattern] = $pattern;
      }
    }
    $this->authorRolePatterns = array_values($this->authorRolePatterns);
  }

  /**
   * Matches the role that a log text shows in front of its author.
   *
   * The placeholder %AuthorWithRole becomes a role and the name of the author,
   * as ComputedTextFieldItemListBase::fakeTranslateRole() decides when the text
   * is shown: an organization, the manager of the log, a supervisor, an
   * administrator or else a creative. The role is matched with the same order
   * of rules. Unlike the shown text, a supervisor or an administrator who
   * takes part in the project is not matched as a creative.
   */
  protected function addAuthorRoleConditions(Condition $or, string $alias, string $text): void {

    $roles = array_keys(array_filter(
      ComputedTextFieldItemListBase::AUTHOR_ROLE_WORDS,
      static fn (string $word) => mb_stripos($word, $text) !== FALSE,
    ));
    $this->loadLogTexts();
    if (!$roles || !$this->authorRolePatterns) {
      return;
    }
    $author = $this->logColumn($alias, 'author');
    $manager = $this->logColumn($alias, 'manager');
    $in = static fn (bool $is) => $is ? 'IN' : 'NOT IN';
    // The rules in their order: a role holds when its rule does and none
    // before it. A creative is what no rule holds for.
    $rules = [
      'organization' => fn (Condition $c, bool $is) => $c->condition($author, $this->ids('user', 'type', 'organization', '='), $in($is)),
      'manager' => static fn (Condition $c, bool $is) => $c->where($is ? "$author = $manager" : "($manager IS NULL OR $author <> $manager)"),
      'supervisor' => fn (Condition $c, bool $is) => $c->condition($author, $this->ids('user', 'roles', 'supervisor', '='), $in($is)),
      'administrator' => fn (Condition $c, bool $is) => $c->condition($author, $this->ids('user', 'roles', 'administrator', '='), $in($is)),
    ];

    foreach ($roles as $kind) {
      $and = new Condition('AND');
      $this->logCondition($and, $alias, 'type', $this->authorRolePatterns, 'IN');
      foreach ($rules as $rule => $add) {
        $add($and, $rule === $kind);
        if ($rule === $kind) {
          break;
        }
      }
      $or->condition($and);
    }
  }

  /**
   * Gets the column of a field in the base table of the log.
   */
  protected function logColumn(string $alias, string $field): string {
    return $alias . '.' . $this->column('log', $field)[1];
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
