<?php

namespace Drupal\logbook\Plugin\views\filter;

use Drupal\logbook\LogPatternMatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared code of the filters that restrict the logs by their log pattern.
 *
 * The filters are attached to the log table and add a condition on the log
 * type, which holds the pattern id.
 */
trait LogPatternFilterTrait {

  /**
   * The pattern matcher.
   */
  protected LogPatternMatcher $matcher;

  /**
   * Constructs the filter.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LogPatternMatcher $matcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->matcher = $matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('logbook.pattern_matcher'));
  }

  /**
   * Restricts the logs to the given pattern ids.
   *
   * An empty list matches nothing; `IN ()` is invalid SQL.
   *
   * @param string[] $ids
   *   The pattern ids.
   */
  protected function restrictTypes(array $ids): void {
    $alias = $this->ensureMyTable();
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    if ($ids === []) {
      $query->addWhereExpression($this->options['group'], '1 = 0');
      return;
    }
    $query->addWhere($this->options['group'], $alias . '.type', $ids, 'IN');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return array_merge(parent::getCacheTags(), ['config:log_pattern_list']);
  }

}
