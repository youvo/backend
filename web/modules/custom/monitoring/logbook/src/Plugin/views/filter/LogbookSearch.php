<?php

namespace Drupal\logbook\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\logbook\Plugin\views\LogMatchTrait;
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
#[ViewsFilter('logbook_search')]
class LogbookSearch extends FilterPluginBase {

  use LogMatchTrait;

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
    // The value is a list of tokens, which says little in the Views UI.
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
      $query->addWhere($this->options['group'], $this->entityCondition($alias, $tags['user'], $tags['project']));
    }
    foreach ($tags['text'] as $text) {
      $query->addWhere($this->options['group'], $this->textCondition($alias, $text));
    }
  }

  /**
   * Matches the logs that contain the text.
   *
   * @param string $alias
   *   The alias of the log table.
   * @param string $text
   *   The text.
   */
  protected function textCondition(string $alias, string $text): Condition {
    $like = '%' . $this->database->escapeLike($text) . '%';
    $or = new Condition('OR');
    $this->logCondition($or, $alias, 'message', $like, 'LIKE');
    $this->logCondition($or, $alias, 'project', $this->ids('project', 'title', $like, 'LIKE'), 'IN');
    // The names of the people and organizations in the log, in every role,
    // and the logs of the projects they own, as a tag of them would find.
    $names = $this->ids('user', 'field_name', $like, 'LIKE');
    foreach (static::PARTICIPANTS as $field) {
      $this->logCondition($or, $alias, $field, clone $names, 'IN');
    }
    $this->logCondition($or, $alias, 'project', $this->ids('project', 'uid', $names, 'IN'), 'IN');
    if ($patterns = $this->patternsByText($text)) {
      $this->logCondition($or, $alias, 'type', $patterns, 'IN');
    }
    return $or;
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
    $patterns = [];
    /** @var \Drupal\logbook\LogTextInterface $log_text */
    foreach ($this->entityTypeManager->getStorage('log_text')->loadMultiple() as $log_text) {
      $words = preg_replace('/%\w+/u', ' ', $log_text->getText()) ?? '';
      if (mb_stripos($words, $text) !== FALSE) {
        $patterns[] = (string) $log_text->get('log_pattern')->target_id;
      }
    }
    return array_values(array_unique(array_filter($patterns)));
  }

}
