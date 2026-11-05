<?php

namespace Drupal\stats;

/**
 * Builds Chart.js chart render elements for the stats page.
 *
 * Each chart also renders its numbers as a visually hidden table for screen
 * readers, without a button to show it.
 */
class StatsCharts {

  /**
   * The series colors, from the yv palette: blue, orange, turquoise, red, grey.
   */
  const array COLORS = ['#3f78b5', '#f79b6a', '#3ec4c2', '#e15554', '#526079'];

  /**
   * The slice colors of doughnuts, distinct for up to eleven slices.
   *
   * The series colors first, then their light variants and dark blue.
   */
  const array COLORS_DOUGHNUT = [
    '#3f78b5',
    '#f79b6a',
    '#3ec4c2',
    '#e15554',
    '#526079',
    '#7e98b5',
    '#ffb793',
    '#96d1cf',
    '#e08b8b',
    '#b8c6d6',
    '#15508e',
  ];

  /**
   * Wraps a chart in a card of the stats grid.
   *
   * @param array<string, mixed> $chart
   *   The chart render element.
   * @param bool $wide
   *   Whether the card spans two columns.
   * @param bool $tall
   *   Whether the card spans two rows, with a taller chart.
   * @param bool $full
   *   Whether the card spans the whole row.
   *
   * @return array<string, mixed>
   *   The card render array.
   */
  public static function card(array $chart, bool $wide = FALSE, bool $tall = FALSE, bool $full = FALSE): array {
    $classes = ['stats-card'];
    if ($wide) {
      $classes[] = 'stats-card--wide';
    }
    if ($full) {
      $classes[] = 'stats-card--full';
    }
    if ($tall) {
      $classes[] = 'stats-card--tall';
      // A square chart keeps room for the plot under a long legend on narrow
      // screens; the CSS max-height caps it on wide ones.
      $chart['#raw_options']['options']['aspectRatio'] = 1;
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => $classes],
      'chart' => $chart,
    ];
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
   *
   * @return array<string, mixed>
   *   The chart render element.
   */
  public static function bar(string $id, string $title, array $labels, array $series, bool $stacked = FALSE, bool $horizontal = FALSE): array {
    $chart = static::chart($id, $title, $horizontal ? 'bar' : 'column', $labels, $series);
    $chart['#stacking'] = $stacked;
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
    $chart['#colors'] = array_slice(static::COLORS_DOUGHNUT, 0, max(1, count($labels)));
    $chart['#legend'] = TRUE;
    $chart['series_0']['#labels'] = array_map('strval', array_values($labels));
    unset($chart['series_0']['#color']);
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
      '#title' => $title,
      // Chart.js's default text color stays readable in Gin's dark mode.
      '#title_color' => '',
      '#accessible_table' => 'invisible',
      '#legend' => count($series) > 1,
      '#legend_position' => 'bottom',
    ];

    $index = 0;
    foreach ($series as $name => $values) {
      $chart['series_' . $index] = [
        '#type' => 'chart_data',
        '#title' => (string) $name,
        '#data' => array_values($values),
        '#color' => static::COLORS[$index % count(static::COLORS)],
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
