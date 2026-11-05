<?php

namespace Drupal\manager\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\BooleanOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filters the creatives by whether they entered a portfolio or website.
 *
 * The filter asks for the existence of a value in either link field. It joins
 * nothing, so a creative with both links still appears once.
 */
#[ViewsFilter('creative_has_link')]
class CreativeHasLink extends BooleanOperator {

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
   * Creates the filter with its services.
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
   * Adds the existence condition for a website or portfolio value.
   */
  public function query(): void {
    $url = $this->database->select('user__field_url', 'link_url');
    $url->addExpression('1');
    $url->where('[link_url].[entity_id] = [users_field_data].[uid]');
    $url->isNotNull('link_url.field_url_value');
    $url->condition('link_url.field_url_value', '', '<>');

    $portfolio = $this->database->select('user__field_portfolio', 'link_portfolio');
    $portfolio->addExpression('1');
    $portfolio->where('[link_portfolio].[entity_id] = [users_field_data].[uid]');
    $portfolio->isNotNull('link_portfolio.field_portfolio_value');
    $portfolio->condition('link_portfolio.field_portfolio_value', '', '<>');

    $condition = $this->database->condition('AND');
    if (empty($this->value)) {
      $condition->notExists($url)->notExists($portfolio);
    }
    else {
      $condition->condition($this->database->condition('OR')->exists($url)->exists($portfolio));
    }
    /** @var \Drupal\views\Plugin\views\query\Sql $query */
    $query = $this->query;
    $query->addWhere($this->options['group'], $condition);
  }

}
