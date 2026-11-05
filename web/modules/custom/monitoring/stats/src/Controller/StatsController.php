<?php

namespace Drupal\stats\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\projects\ProjectTransition;
use Drupal\stats\Stats\ProjectStats;
use Drupal\stats\StatsCharts;
use Drupal\stats\StatsPeriod;
use Drupal\stats\StatsScope;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the stats page.
 */
class StatsController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The comparison with the period right before.
   */
  const string COMPARE_PREVIOUS = 'previous';

  /**
   * The comparison with the same period one year earlier.
   */
  const string COMPARE_LAST_YEAR = 'last_year';

  /**
   * The U+2212 minus sign used in changes.
   */
  const string MINUS = '−';

  /**
   * Constructs the stats controller.
   */
  public function __construct(
    protected ProjectStats $projectStats,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('stats.project_stats'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('database'),
    );
  }

  /**
   * Builds the stats page.
   */
  public function page(Request $request): array {

    $query = $request->query->all();
    $timezone = new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default'));
    $now = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($timezone);
    $period = StatsPeriod::fromQuery($query, $timezone, $now);
    $scope = StatsScope::fromQuery($query, $this->database);
    $compare = ($query['compare'] ?? NULL) === static::COMPARE_LAST_YEAR ? static::COMPARE_LAST_YEAR : static::COMPARE_PREVIOUS;

    $is_all = $period->query() === ['period' => 'all'];
    $comparison = NULL;
    if (!$is_all) {
      $comparison = $compare === static::COMPARE_LAST_YEAR ? $period->sameLastYear() : $period->previous();
    }

    $current = [
      'flow' => $this->projectStats->flow($period, $scope),
      'cohort' => $this->projectStats->cohort($period, $scope),
      'medians' => $this->projectStats->stageMedians($period, $scope),
    ];
    $before = $comparison ? [
      'flow' => $this->projectStats->flow($comparison, $scope),
      'cohort' => $this->projectStats->cohort($comparison, $scope),
      'medians' => $this->projectStats->stageMedians($comparison, $scope),
    ] : NULL;

    $versus = $comparison ? $this->t('vs @period', ['@period' => $comparison->label()]) : NULL;
    $kpis = [
      $this->tile('published', $this->t('Published'), $current['cohort']['published'], $before['cohort']['published'] ?? NULL, 'count', $versus),
      $this->tile('mediated', $this->t('Mediated'), $current['cohort']['mediated'], $before['cohort']['mediated'] ?? NULL, 'count', $versus),
      $this->tile('mediation-rate', $this->t('Mediation rate (cohort)'), $current['cohort']['mediation_rate'], $before['cohort']['mediation_rate'] ?? NULL, 'rate', $versus),
      $this->tile('median', $this->t('Median publish → mediate'), $current['medians']['publish_mediate'], $before['medians']['publish_mediate'] ?? NULL, 'days', $versus),
    ];

    return [
      '#theme' => 'stats_page',
      '#period_bar' => $this->periodBar($period, $scope, $compare, $timezone, $now),
      '#kpis' => $kpis,
      '#sections' => [
        'projects' => $this->projectsSection($period, $comparison, $current, $before),
      ],
      '#attached' => ['library' => ['stats/page']],
      '#cache' => [
        'tags' => ['project_list', 'log_list', 'feedback_list', 'user_list', 'course_progress_list'],
        'contexts' => ['url.query_args', 'user.permissions'],
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Builds the projects section: flow, cohort funnel and stage medians.
   *
   * @param \Drupal\stats\StatsPeriod $period
   *   The period.
   * @param \Drupal\stats\StatsPeriod|null $comparison
   *   The comparison period, NULL if there is none.
   * @param array<string, mixed> $current
   *   The project stats of the period.
   * @param array<string, mixed>|null $before
   *   The project stats of the comparison period, NULL if there is none.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function projectsSection(StatsPeriod $period, ?StatsPeriod $comparison, array $current, ?array $before): array {

    $labels = array_map('strval', array_keys($period->buckets()));
    $transitions = [
      ProjectTransition::Submit->value => $this->t('Submitted'),
      ProjectTransition::Publish->value => $this->t('Published'),
      ProjectTransition::Mediate->value => $this->t('Mediated'),
      ProjectTransition::Complete->value => $this->t('Completed'),
      ProjectTransition::Decline->value => $this->t('Declined'),
    ];

    // Each transition is followed by its lighter twin of the comparison
    // period, aligned by bucket position.
    $series = [];
    $colors = [];
    $index = 0;
    foreach ($transitions as $transition => $name) {
      $series[(string) $name] = array_values($current['flow'][$transition]);
      $colors[(string) $name] = StatsCharts::COLORS[$index];
      if ($comparison && $before) {
        $twin = (string) $this->t('@series (@period)', ['@series' => $name, '@period' => $comparison->label()]);
        $values = array_slice(array_values($before['flow'][$transition]), 0, count($labels));
        $series[$twin] = array_pad($values, count($labels), 0);
        $colors[$twin] = StatsCharts::COLORS_LIGHT[$index];
      }
      $index++;
    }

    $cohort = $current['cohort'];
    $funnel_labels = [
      (string) $this->t('Published'),
      (string) $this->t('Mediated'),
      (string) $this->t('Completed'),
    ];
    $funnel = [
      (string) $this->t('Projects') => [$cohort['published'], $cohort['mediated'], $cohort['completed']],
    ];

    $medians = $current['medians'];
    $stage_labels = [
      (string) $this->t('Submit → publish'),
      (string) $this->t('Publish → mediate'),
      (string) $this->t('Mediate → complete'),
    ];
    $stages = [
      (string) $this->t('Days') => [
        $medians['submit_publish'],
        $medians['publish_mediate'],
        $medians['mediate_complete'],
      ],
    ];

    $flow_chart = StatsCharts::bar('stats-flow', (string) $this->t('Project flow'), $labels, $series, colors: $colors);
    $funnel_title = (string) $this->t('Cohort: published in @period', ['@period' => $period->label()]);
    $funnel_chart = StatsCharts::bar('stats-funnel', $funnel_title, $funnel_labels, $funnel, horizontal: TRUE);
    $stages_title = (string) $this->t('Median days per stage');
    $stages_chart = StatsCharts::bar('stats-stages', $stages_title, $stage_labels, $stages, horizontal: TRUE);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stats-section', 'stats-grid']],
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Projects'),
        '#attributes' => ['class' => ['stats-section__heading']],
      ],
      'flow' => StatsCharts::card($flow_chart, wide: TRUE, tall: TRUE),
      'funnel' => StatsCharts::card($funnel_chart),
      'stages' => StatsCharts::card($stages_chart),
    ];
  }

  /**
   * Builds a KPI tile with its change against the comparison.
   *
   * @param string $key
   *   The tile key.
   * @param mixed $label
   *   The label.
   * @param int|float|null $value
   *   The value, NULL if undefined.
   * @param int|float|null $before
   *   The value of the comparison period, NULL if undefined.
   * @param string $type
   *   One of count, rate (a fraction) and days.
   * @param mixed $versus
   *   The text naming the comparison period.
   *
   * @return array<string, mixed>
   *   The tile.
   */
  protected function tile(string $key, mixed $label, int|float|null $value, int|float|null $before, string $type, mixed $versus): array {

    $text = '–';
    if ($value !== NULL) {
      $text = match ($type) {
        'count' => (string) $value,
        'rate' => round($value * 100) . ' %',
        default => number_format($value, 1, '.', '') . ' d',
      };
    }

    $delta = NULL;
    $direction = 'same';
    if ($value !== NULL && $before !== NULL) {
      $difference = match ($type) {
        'count' => $value - $before,
        'rate' => round($value * 100) - round($before * 100),
        default => round($value - $before, 1),
      };
      $unit = match ($type) {
        'count' => '',
        'rate' => ' pp',
        default => ' d',
      };
      $number = match ($type) {
        'days' => number_format(abs($difference), 1, '.', ''),
        default => (string) abs($difference),
      };
      if ($difference == 0) {
        $delta = '±' . $number . $unit;
      }
      else {
        $direction = $difference > 0 ? 'up' : 'down';
        $delta = ($difference > 0 ? '+' : static::MINUS) . $number . $unit;
      }
    }

    return [
      'key' => $key,
      'label' => $label,
      'value' => $text,
      'delta' => $delta,
      'direction' => $direction,
      'versus' => $delta !== NULL ? $versus : NULL,
    ];
  }

  /**
   * Builds the period bar: presets, navigation and the filter form.
   *
   * @return array<string, mixed>
   *   The variables of the bar.
   */
  protected function periodBar(StatsPeriod $period, StatsScope $scope, string $compare, \DateTimeZone $timezone, \DateTimeImmutable $now): array {

    $extra = [];
    if ($compare !== static::COMPARE_PREVIOUS) {
      $extra['compare'] = $compare;
    }
    if ($scope->managerId() !== NULL) {
      $extra['manager'] = (string) $scope->managerId();
    }
    $link = fn(array $query): string => Url::fromRoute('stats.overview', [], ['query' => $query + $extra])->toString();

    $presets = [];
    $labels = [
      'all' => $this->t('All time'),
      'year' => NULL,
      'quarter' => NULL,
      'month' => NULL,
    ];
    foreach ($labels as $type => $label) {
      $preset = StatsPeriod::fromQuery(['period' => $type], $timezone, $now);
      $presets[] = [
        'label' => $label ?? $preset->label(),
        'url' => $link($preset->query()),
        'active' => $preset->query() === $period->query(),
      ];
    }

    $is_all = $period->query() === ['period' => 'all'];
    $previous = $period->previous();
    $next = $period->next();
    $navigation = [];
    if (!$is_all) {
      $navigation['previous'] = ['label' => '‹ ' . $previous->label(), 'url' => $link($previous->query())];
      // No period beyond the current time.
      $navigation['next'] = $next->from() <= $now ? ['label' => $next->label() . ' ›', 'url' => $link($next->query())] : NULL;
    }

    $managers = [['id' => '', 'name' => $this->t('All'), 'selected' => $scope->managerId() === NULL]];
    $query = $this->database->select('user__roles', 'r');
    $query->innerJoin('users_field_data', 'u', 'u.uid = r.entity_id');
    $rows = $query->fields('u', ['uid', 'name'])
      ->condition('r.roles_target_id', StatsScope::MANAGER_ROLE)
      ->condition('r.deleted', 0)
      ->orderBy('u.name')
      ->execute()
      ->fetchAll();
    foreach ($rows as $row) {
      $managers[] = [
        'id' => (int) $row->uid,
        'name' => $row->name,
        'selected' => $scope->managerId() === (int) $row->uid,
      ];
    }

    return [
      'heading' => $period->label(),
      'presets' => $presets,
      'navigation' => $navigation,
      'from' => $period->from()->format('Y-m-d'),
      'to' => $period->to()->modify('-1 day')->format('Y-m-d'),
      'compare' => $compare,
      'compare_options' => [
        static::COMPARE_PREVIOUS => $this->t('Previous period'),
        static::COMPARE_LAST_YEAR => $this->t('Same period last year'),
      ],
      'managers' => $managers,
    ];
  }

}
