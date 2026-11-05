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
   *
   * @return array<string, mixed>
   *   The card render array.
   */
  public static function card(array $chart, bool $wide = FALSE, bool $tall = FALSE, bool $compact = FALSE): array {
    $title_id = $chart['#id'] . '-title';
    $attributes = [
      'class' => ['stats-card'],
      'aria-labelledby' => $title_id,
    ];
    if ($wide) {
      $attributes['data-wide'] = '';
    }
    if ($tall) {
      $attributes['data-tall'] = '';
    }
    if ($compact) {
      $attributes['data-compact'] = '';
    }
    // The stylesheet sizes the figure of the chart. Horizontal bars take a
    // height by their number of rows, a chart without data a low height.
    if ($chart['#chart_type'] === 'bar') {
      $attributes['data-rows'] = '';
      $attributes['style'] = '--stats-rows: ' . count($chart['xaxis']['#labels']);
    }
    if ($chart['#chart_type'] === 'donut') {
      $attributes['data-doughnut'] = '';
    }
    if (static::isEmpty($chart)) {
      $attributes['data-empty'] = '';
    }
    $title = $chart['#title'];
    return [
      '#type' => 'html_tag',
      '#tag' => 'section',
      '#attributes' => $attributes,
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $title,
        '#attributes' => ['id' => $title_id, 'class' => ['stats-card-title']],
      ],
      'chart' => $chart,
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
   * @param string $title
   *   The title.
   * @param string[] $labels
   *   The category labels.
   * @param array<string, array<int, int|float|null>> $series
   *   The values per series name, in the order of the labels.
   * @param bool $stacked
   *   Whether to stack the series.
   * @param bool $horizontal
   *   Whether to draw horizontal bars.
   * @param int|null $max
   *   The fixed maximum of the value axis, which then starts at 0.
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  public static function bar(string $id, string $title, array $labels, array $series, bool $stacked = FALSE, bool $horizontal = FALSE, ?int $max = NULL): array {
    $chart = static::chart($id, $title, $horizontal ? 'bar' : 'column', $labels, $series);
    $chart['#stacking'] = $stacked;
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
   * @param string $title
   *   The title.
   * @param string[] $labels
   *   The slice labels.
   * @param array<int, int|float|null> $values
   *   The values in the order of the labels.
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  public static function doughnut(string $id, string $title, array $labels, array $values): array {
    $chart = static::chart($id, $title, 'donut', $labels, [$title => $values]);
    $chart['#legend'] = TRUE;
    $chart['series_0']['#labels'] = array_map('strval', array_values($labels));
    return $chart;
  }

  /**
   * Builds a line chart.
   *
   * @param string $id
   *   The HTML id of the chart.
   * @param string $title
   *   The title.
   * @param string[] $labels
   *   The category labels.
   * @param array<string, array<int, int|float|null>> $series
   *   The values per series name, in the order of the labels.
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  public static function line(string $id, string $title, array $labels, array $series): array {
    return static::chart($id, $title, 'line', $labels, $series);
  }

  /**
   * Builds the chart element shared by all chart types.
   *
   * @param string $id
   *   The HTML id of the chart.
   * @param string $title
   *   The title.
   * @param string $type
   *   The chart type id of the charts module.
   * @param string[] $labels
   *   The category labels.
   * @param array<string, array<int, int|float|null>> $series
   *   The values per series name, in the order of the labels.
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  protected static function chart(string $id, string $title, string $type, array $labels, array $series): array {

    $chart = [
      '#type' => 'chart',
      '#id' => $id,
      '#chart_id' => $id,
      '#chart_library' => 'chartjs',
      '#chart_type' => $type,
      // The title labels the figure and captions the table; the card shows
      // it, so the canvas draws none.
      '#title' => $title,
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
