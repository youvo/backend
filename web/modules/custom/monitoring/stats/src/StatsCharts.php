<?php

namespace Drupal\stats;

/**
 * Builds Chart.js chart render elements for the stats page.
 *
 * Each chart also renders its numbers as a visually hidden table for screen
 * readers, without a button to show it. The look and the colors come from
 * js/stats-charts.js and the theme tokens; the charts here hold only the data
 * and the options of the single chart.
 */
class StatsCharts {

  /**
   * Wraps a chart in a card of the stats grid.
   *
   * The card shows the chart's title as its label, so the chart draws none.
   * The chart keeps it for its figure label and the caption of its table.
   *
   * @param array<string, mixed> $chart
   *   The chart render element.
   * @param bool $wide
   *   Whether the card spans two columns.
   * @param bool $tall
   *   Whether the card spans two rows, with a chart that fills it.
   * @param bool $compact
   *   Whether the card holds a small chart of a fixed low height.
   * @param string|\Stringable|null $description
   *   A short line under the title that explains the chart.
   * @param string|\Stringable|null $value
   *   A large value over the chart. The card then shows a chart without data
   *   not at all.
   *
   * @return array<string, mixed>
   *   The card render array.
   */
  public static function card(array $chart, bool $wide = FALSE, bool $tall = FALSE, bool $compact = FALSE, string|\Stringable|null $description = NULL, string|\Stringable|null $value = NULL): array {
    $title_id = $chart['#id'] . '-title';
    $description_id = $chart['#id'] . '-description';
    $attributes = [
      'class' => ['stats-card'],
      'aria-labelledby' => $title_id,
    ];
    if ($description !== NULL) {
      $attributes['aria-describedby'] = $description_id;
    }
    if ($wide) {
      $attributes['data-wide'] = '';
    }
    if ($tall) {
      $attributes['data-tall'] = '';
    }
    if ($compact) {
      $attributes['data-compact'] = '';
    }
    // The stylesheet sizes the figure by the kind of chart. Horizontal bars
    // take a height by their number of rows, a chart without data a low one.
    if ($chart['#chart_type'] === 'bar') {
      $attributes['data-kind'] = 'hbar';
      $attributes['style'] = '--stats-rows: ' . count($chart['xaxis']['#labels']);
    }
    if ($chart['#chart_type'] === 'donut') {
      $attributes['data-kind'] = 'doughnut';
    }
    if (static::isEmpty($chart)) {
      $attributes['data-empty'] = '';
    }
    $card = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => $attributes,
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['stats-card-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $chart['#title'],
          '#attributes' => ['id' => $title_id, 'class' => ['stats-card-title']],
        ],
      ],
    ];
    if ($description !== NULL) {
      $card['header']['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $description,
        '#attributes' => ['id' => $description_id, 'class' => ['stats-card-description']],
      ];
    }
    if ($value !== NULL) {
      $card['value'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $value,
        '#attributes' => ['class' => ['stats-card-value']],
      ];
    }
    $card['chart'] = $chart;
    if ($value !== NULL && static::isEmpty($chart)) {
      unset($card['chart']);
    }
    return $card;
  }

  /**
   * Builds a section of the stats grid: a header over its cards.
   *
   * @param string $key
   *   The key of the section, which gives the id of its title.
   * @param string|\Stringable $title
   *   The title.
   * @param array<string, mixed> $cards
   *   The cards keyed by name.
   * @param array<string, mixed> $attributes
   *   More attributes of the section.
   * @param string|\Stringable|null $hint
   *   A muted line under the title.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  public static function section(string $key, string|\Stringable $title, array $cards, array $attributes = [], string|\Stringable|null $hint = NULL): array {
    $title_id = 'stats-section-' . $key;
    $attributes['class'] = ['stats-section', ...($attributes['class'] ?? [])];
    $section = [
      '#type' => 'html_tag',
      '#tag' => 'section',
      '#attributes' => ['aria-labelledby' => $title_id] + $attributes,
      'header' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['class' => ['stats-section-header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $title,
          '#attributes' => ['id' => $title_id, 'class' => ['stats-section-title']],
        ],
      ],
    ];
    if ($hint !== NULL) {
      $section['header']['hint'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $hint,
        '#attributes' => ['class' => ['stats-section-hint']],
      ];
    }
    return $section + $cards;
  }

  /**
   * Builds a grid cell of KPI tiles.
   *
   * @param array<int, array<string, mixed>> $tiles
   *   The tiles from tile().
   *
   * @return array<string, mixed>
   *   The tiles render array.
   */
  public static function tiles(array $tiles): array {
    return [
      '#theme' => 'stats_tiles',
      '#tiles' => $tiles,
    ];
  }

  /**
   * Builds the line under a chart with tile values.
   *
   * The descriptions of the tiles follow in a muted line below.
   *
   * @param array<string, mixed> ...$tiles
   *   The tiles from tile().
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public static function foot(array ...$tiles): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<div class="stats-card-foot">'
      . '<p class="stats-card-foot-values">{% for tile in tiles %}<span data-foot="{{ tile.key }}">{{ tile.label }}: <b class="stats-card-foot-value">{{ tile.value }}</b></span>{% if not loop.last %}<span aria-hidden="true"> · </span>{% endif %}{% endfor %}</p>'
      . '{% for tile in tiles|filter(tile => tile.description) %}<p class="stats-card-foot-description">{{ tile.description }}</p>{% endfor %}'
      . '</div>',
      '#context' => ['tiles' => $tiles],
    ];
  }

  /**
   * Builds a KPI tile.
   *
   * @param string $key
   *   The tile key.
   * @param string|\Stringable $label
   *   The label.
   * @param int|float|null $value
   *   The count, or the rate as a fraction, NULL if undefined.
   * @param string|\Stringable|null $description
   *   A short line that explains the value, if any.
   * @param bool $rate
   *   Whether the value is a rate, shown in percent.
   *
   * @return array<string, mixed>
   *   The tile.
   */
  public static function tile(string $key, string|\Stringable $label, int|float|null $value, string|\Stringable|null $description = NULL, bool $rate = FALSE): array {
    $text = '–';
    if ($value !== NULL) {
      $text = $rate ? round($value * 100) . ' %' : (string) $value;
    }
    return [
      'key' => $key,
      'label' => $label,
      'value' => $text,
      'description' => $description,
    ];
  }

  /**
   * Checks whether a chart holds no value other than 0.
   *
   * The script then draws a note instead of the chart, as in its isEmpty().
   *
   * @param array<string, mixed> $chart
   *   The chart render element.
   */
  public static function isEmpty(array $chart): bool {
    foreach ($chart as $key => $child) {
      if (str_starts_with((string) $key, 'series_')) {
        foreach ($child['#data'] as $value) {
          if ($value !== NULL && (float) $value !== 0.0) {
            return FALSE;
          }
        }
      }
    }
    return TRUE;
  }

  /**
   * Builds a bar chart.
   *
   * @param string $id
   *   The HTML id of the chart.
   * @param string|\Stringable $title
   *   The title.
   * @param array<int, string|\Stringable> $labels
   *   The category labels.
   * @param array<string, array<int|string, int|float|null>> $series
   *   The values per series name, in the order of the labels.
   * @param bool $horizontal
   *   Whether to draw horizontal bars.
   * @param int|null $max
   *   The fixed maximum of the value axis, which then starts at 0.
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  public static function bar(string $id, string|\Stringable $title, array $labels, array $series, bool $horizontal = FALSE, ?int $max = NULL): array {
    $chart = static::chart($id, $title, $horizontal ? 'bar' : 'column', $labels, $series);
    if ($max !== NULL) {
      $chart['#raw_options']['options']['scales'][$horizontal ? 'x' : 'y'] += [
        'min' => 0,
        'max' => $max,
      ];
      $chart['#raw_options']['options']['scales'][$horizontal ? 'x' : 'y']['ticks'] += [
        'stepSize' => 1,
        'maxTicksLimit' => $max + 1,
      ];
    }
    return $chart;
  }

  /**
   * Builds a doughnut chart of one series.
   *
   * @param string $id
   *   The HTML id of the chart.
   * @param string|\Stringable $title
   *   The title.
   * @param array<int, string|\Stringable> $labels
   *   The slice labels.
   * @param array<int|string, int|float|null> $values
   *   The values in the order of the labels.
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  public static function doughnut(string $id, string|\Stringable $title, array $labels, array $values): array {
    $chart = static::chart($id, $title, 'donut', $labels, [(string) $title => $values]);
    $chart['#legend'] = TRUE;
    $chart['series_0']['#labels'] = array_map('strval', array_values($labels));
    return $chart;
  }

  /**
   * Builds a line chart.
   *
   * @param string $id
   *   The HTML id of the chart.
   * @param string|\Stringable $title
   *   The title.
   * @param array<int, string|\Stringable> $labels
   *   The category labels.
   * @param array<string, array<int|string, int|float|null>> $series
   *   The values per series name, in the order of the labels.
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  public static function line(string $id, string|\Stringable $title, array $labels, array $series): array {
    return static::chart($id, $title, 'line', $labels, $series);
  }

  /**
   * Builds the chart element shared by all chart types.
   *
   * @param string $id
   *   The HTML id of the chart.
   * @param string|\Stringable $title
   *   The title.
   * @param string $type
   *   The chart type id of the charts module.
   * @param array<int, string|\Stringable> $labels
   *   The category labels.
   * @param array<string, array<int|string, int|float|null>> $series
   *   The values per series name, in the order of the labels.
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  protected static function chart(string $id, string|\Stringable $title, string $type, array $labels, array $series): array {

    $chart = [
      '#type' => 'chart',
      '#id' => $id,
      '#chart_id' => $id,
      '#chart_library' => 'chartjs',
      '#chart_type' => $type,
      // The title labels the figure and captions the table; the card shows
      // it, so the canvas draws none.
      '#title' => (string) $title,
      '#title_color' => '',
      '#accessible_table' => 'invisible',
      '#legend' => count($series) > 1,
      '#legend_position' => 'bottom',
      '#raw_options' => [
        'options' => [
          'plugins' => ['title' => ['display' => FALSE]],
          // The value axis counts in whole steps; the script styles it.
          'scales' => $type === 'donut' ? [] : [
            $type === 'bar' ? 'x' : 'y' => ['ticks' => ['precision' => 0]],
          ],
        ],
      ],
    ];

    $index = 0;
    foreach ($series as $name => $values) {
      $chart['series_' . $index] = [
        '#type' => 'chart_data',
        '#title' => (string) $name,
        '#data' => array_values($values),
      ];
      $index++;
    }

    $chart['xaxis'] = [
      '#type' => 'chart_xaxis',
      '#labels' => array_map('strval', array_values($labels)),
    ];

    return $chart;
  }

}
