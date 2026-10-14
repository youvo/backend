<?php

namespace Drupal\logbook\Plugin\views\filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\logbook\LogPatternInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters the logs by the log patterns that the logbook lists.
 *
 * The logbook lists the logs of enabled, observable log patterns. The flags of
 * the patterns decide, not a list of ids in the view: a pattern that is changed
 * or created later is picked up, and the view does not depend on any pattern,
 * so deleting a pattern does not delete the view.
 *
 * Not exposed, the filter limits the list to those patterns. Exposed, it is a
 * select of those patterns and limits the list to the chosen one.
 */
#[ViewsFilter('log_pattern_scope')]
class LogPatternScope extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * Constructs the filter.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions(): array {
    $this->valueOptions = $this->listedPatterns();
    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    $listed = array_keys($this->listedPatterns());
    $chosen = array_filter((array) $this->value);
    $ids = $chosen ? array_values(array_intersect($listed, $chosen)) : $listed;
    $alias = $this->ensureMyTable();
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    // An empty list matches nothing; `IN ()` is invalid SQL.
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
    return array_merge(parent::getCacheTags(), $this->entityTypeManager->getDefinition('log_pattern')->getListCacheTags());
  }

  /**
   * Gets the labels of the listed patterns, keyed by pattern id.
   *
   * @return array<string, string>
   *   The labels, without the "Project " prefix the pills do not show either.
   */
  protected function listedPatterns(): array {
    $options = [];
    /** @var \Drupal\logbook\LogPatternInterface $pattern */
    foreach ($this->entityTypeManager->getStorage('log_pattern')->loadMultiple() as $id => $pattern) {
      if ($pattern instanceof LogPatternInterface && $pattern->isEnabled() && $pattern->isObservable()) {
        $options[(string) $id] = trim(str_replace('Project ', '', (string) $pattern->label()));
      }
    }
    asort($options);
    return $options;
  }

}
