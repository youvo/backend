<?php

namespace Drupal\stats\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectTransition;
use Drupal\stats\Activity\ActivitySnapshots;
use Drupal\stats\Stats\ActivityStats;
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
    protected ActivityStats $activityStats,
    protected ActivitySnapshots $activitySnapshots,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected Connection $database,
  ) {}

  /**
   * Creates the stats controller with its services.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('stats.project_stats'),
      $container->get('stats.activity_stats'),
      $container->get('stats.activity_snapshots'),
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
      'first_application' => $this->activityStats->firstApplicationMedian($period, $scope, $this->projectStats->publishRows($period, $scope)),
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
        'growth' => $this->growthSection($period, $scope),
        'projects' => $this->projectsSection($period, $comparison, $current, $before),
        'applications' => $this->applicationsSection($period, $comparison, $scope, $versus),
        'creatives' => $this->creativesSection($period, $comparison, $versus),
        'organizations' => $this->organizationsSection($period, $scope),
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
      (string) $this->t('Publish → 1st application'),
      (string) $this->t('Publish → mediate'),
      (string) $this->t('Mediate → complete'),
    ];
    $stages = [
      (string) $this->t('Days') => [
        $medians['submit_publish'],
        $current['first_application'],
        $medians['publish_mediate'],
        $medians['mediate_complete'],
      ],
    ];

    $flow_chart = StatsCharts::bar('stats-flow', (string) $this->t('Project flow'), $labels, $series, colors: $colors);
    $funnel_title = (string) $this->t('Cohort: published in @period', ['@period' => $period->label()]);
    $funnel_chart = StatsCharts::bar('stats-funnel', $funnel_title, $funnel_labels, $funnel, horizontal: TRUE);
    $stages_title = (string) $this->t('Median days per stage');
    $stages_chart = StatsCharts::bar('stats-stages', $stages_title, $stage_labels, $stages, horizontal: TRUE);

    $heading = $this->heading($this->t('Projects'), $this->t('old: brokered and finished per month, broker rate, days to brokering'));
    return $this->section($heading, [
      'flow' => StatsCharts::card($flow_chart, wide: TRUE, tall: TRUE),
      'funnel' => StatsCharts::card($funnel_chart),
      'stages' => StatsCharts::card($stages_chart),
    ]);
  }

  /**
   * Builds the growth section: running totals of users and projects.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function growthSection(StatsPeriod $period, StatsScope $scope): array {

    $labels = array_map('strval', array_keys($period->buckets()));
    $registrations = $this->activityStats->registrations($period);
    $projects = $this->projectStats->cumulative($period, $scope);
    $series = [
      (string) $this->t('Creatives') => array_values($registrations['creatives_total']),
      (string) $this->t('Organizations') => array_values($registrations['organizations_total']),
      (string) $this->t('Projects mediated') => array_values($projects['mediated']),
      (string) $this->t('Projects completed') => array_values($projects['completed']),
    ];
    $chart = StatsCharts::line('stats-growth', (string) $this->t('Running totals'), $labels, $series);

    $heading = $this->heading($this->t('Growth'), $this->t('old: registered creatives, finished and brokered projects (cumulative)'));
    return $this->section($heading, [
      'growth' => StatsCharts::card($chart, full: TRUE),
    ]);
  }

  /**
   * Builds the applications section: applications, applicants and tiles.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function applicationsSection(StatsPeriod $period, ?StatsPeriod $comparison, StatsScope $scope, mixed $versus): array {

    $labels = array_map('strval', array_keys($period->buckets()));
    $applications = $this->activityStats->applications($period, $scope);
    $engaged = $this->activityStats->engagedCreatives($period, $scope);
    $returned = $this->activityStats->returnAfterRejection($period, $scope);
    $before = $comparison ? [
      'applications' => $this->activityStats->applications($comparison, $scope),
      'engaged' => $this->activityStats->engagedCreatives($comparison, $scope),
      'returned' => $this->activityStats->returnAfterRejection($comparison, $scope),
    ] : NULL;

    $series = [
      (string) $this->t('Applications') => array_values($applications['per_bucket']),
      (string) $this->t('Distinct applicants') => array_values($applications['active_per_bucket']),
    ];
    $chart = StatsCharts::bar('stats-applications', (string) $this->t('Applications and applicants'), $labels, $series);
    $tiles = [
      $this->tile('active-applicants', $this->t('Active applicants'), $applications['active'], $before['applications']['active'] ?? NULL, 'count', $versus),
      $this->tile('first-time-applicants', $this->t('First-time applicants'), $applications['first_time'], $before['applications']['first_time'] ?? NULL, 'count', $versus),
      $this->tile('engaged-creatives', $this->t('Engaged (mediated)'), $engaged, $before['engaged'] ?? NULL, 'count', $versus),
      $this->tile('came-back', $this->t('Came back after rejection'), $returned, $before['returned'] ?? NULL, 'rate', $versus),
    ];

    $heading = $this->heading($this->t('Applications'), $this->t('old: interests per month, distinct interested creatives'));
    return $this->section($heading, [
      'applications' => StatsCharts::card($chart, wide: TRUE),
      'tiles' => $this->tiles($tiles),
    ]);
  }

  /**
   * Builds the creatives section: activation, first application, activity.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function creativesSection(StatsPeriod $period, ?StatsPeriod $comparison, mixed $versus): array {

    $labels = array_map('strval', array_keys($period->buckets()));
    $activation = $this->activityStats->activation($period);
    $activation_before = $comparison ? $this->activityStats->activation($comparison) : NULL;
    $series = [
      (string) $this->t('Registered') => array_values($activation['registered']),
      (string) $this->t('Applied within 30 days') => array_values($activation['activated']),
    ];
    $activation_chart = StatsCharts::bar('stats-activation', (string) $this->t('Registrations and activation'), $labels, $series);
    $activation_card = StatsCharts::card($activation_chart, wide: TRUE);
    $rate = $this->tile('activation-rate', $this->t('Activation rate'), $activation['rate'], $activation_before['rate'] ?? NULL, 'rate', $versus);
    $activation_card['foot'] = $this->foot($rate);

    $histogram = $this->activityStats->firstApplicationHistogram($period);
    $histogram_labels = [
      (string) $this->t('≤ 1 d'),
      (string) $this->t('≤ 7 d'),
      (string) $this->t('≤ 30 d'),
      (string) $this->t('≤ 90 d'),
      (string) $this->t('≤ 1 year'),
      (string) $this->t('Later'),
      (string) $this->t('Never'),
    ];
    $histogram_title = (string) $this->t('First application after registration');
    $histogram_chart = StatsCharts::bar('stats-first-application', $histogram_title, $histogram_labels, [
      (string) $this->t('Creatives') => array_values($histogram),
    ]);

    // Buckets without a snapshot hold NULL and leave a gap in the line.
    $activity = $this->activitySnapshots->series($period);
    $activity_before = $comparison ? $this->activitySnapshots->series($comparison) : NULL;
    $activity_series = [
      (string) $this->t('Creatives') => array_values(array_column($activity['creative'], 'active_30d')),
      (string) $this->t('Organizations') => array_values(array_column($activity['organization'], 'active_30d')),
    ];
    $activity_chart = StatsCharts::line('stats-activity', (string) $this->t('Active users, last 30 days'), $labels, $activity_series);
    $tiles = [
      $this->tile('active-creatives', $this->t('Active creatives (30 d)'), $this->lastActive($activity['creative']), $activity_before ? $this->lastActive($activity_before['creative']) : NULL, 'count', $versus),
      $this->tile('active-organizations', $this->t('Active organizations (30 d)'), $this->lastActive($activity['organization']), $activity_before ? $this->lastActive($activity_before['organization']) : NULL, 'count', $versus),
    ];

    $heading = $this->heading($this->t('Creatives'), $this->t('old: registrations and returns, relative returns, first interest after registration, daily visits'));
    return $this->section($heading, [
      'activation' => $activation_card,
      'first_application' => StatsCharts::card($histogram_chart),
      'activity' => StatsCharts::card($activity_chart, wide: TRUE),
      'tiles' => $this->tiles($tiles),
    ]);
  }

  /**
   * Builds the organizations section: prospect funnel and reasons.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function organizationsSection(StatsPeriod $period, StatsScope $scope): array {

    $funnel = $this->activityStats->prospectFunnel($period);
    $funnel_labels = [
      (string) $this->t('Created'),
      (string) $this->t('Managed'),
      (string) $this->t('First project published'),
    ];
    $funnel_chart = StatsCharts::bar('stats-prospects', (string) $this->t('Prospect funnel'), $funnel_labels, [
      (string) $this->t('Organizations') => array_values($funnel),
    ], horizontal: TRUE);
    $funnel_card = StatsCharts::card($funnel_chart);
    $repeat = $this->activityStats->repeatOrganizations($period, $scope);
    $funnel_card['foot'] = $this->foot($this->tile('repeat-organizations', $this->t('Repeat organizations'), $repeat, NULL, 'rate', NULL));

    $declines = $this->activityStats->reasons($period, $scope, 'project_decline');
    $decline_labels = array_map(fn($reason) => (string) (ProjectDeclineReason::tryFrom($reason)?->label() ?? $reason), array_keys($declines));
    $decline_chart = StatsCharts::doughnut('stats-decline-reasons', (string) $this->t('Decline reasons'), $decline_labels, array_values($declines));
    $archives = $this->activityStats->reasons($period, $scope, 'project_archive');
    $archive_labels = array_map(fn($reason) => (string) (ProjectArchiveReason::tryFrom($reason)?->label() ?? $reason), array_keys($archives));
    $archive_chart = StatsCharts::doughnut('stats-archive-reasons', (string) $this->t('Archive reasons'), $archive_labels, array_values($archives));

    $heading = $this->heading($this->t('Organizations and reasons'), $this->t('old: proposal process funnel, organizations with several projects'));
    return $this->section($heading, [
      'prospects' => $funnel_card,
      'decline_reasons' => StatsCharts::card($decline_chart),
      'archive_reasons' => StatsCharts::card($archive_chart),
    ]);
  }

  /**
   * Gets the active_30d of the last bucket with a snapshot.
   *
   * @param array<string, array{active_1d: ?float, active_30d: ?int}> $buckets
   *   The activity of a role per bucket.
   */
  protected function lastActive(array $buckets): ?int {
    foreach (array_reverse($buckets) as $bucket) {
      if ($bucket['active_30d'] !== NULL) {
        return $bucket['active_30d'];
      }
    }
    return NULL;
  }

  /**
   * Builds a section of the stats grid.
   *
   * @param array<string, mixed> $heading
   *   The heading render array.
   * @param array<string, mixed> $cards
   *   The cards keyed by name.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function section(array $heading, array $cards): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stats-section', 'stats-grid']],
      'heading' => $heading,
    ] + $cards;
  }

  /**
   * Builds a section heading with a note on the old graphs it replaces.
   *
   * @return array<string, mixed>
   *   The heading render array.
   */
  protected function heading(mixed $title, mixed $note): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<h3 class="stats-section__heading">{{ title }} <small class="stats-section__note">{{ note }}</small></h3>',
      '#context' => ['title' => $title, 'note' => $note],
    ];
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
  protected function tiles(array $tiles): array {
    return [
      '#theme' => 'stats_tiles',
      '#tiles' => $tiles,
      '#attributes' => ['class' => ['stats-tiles']],
    ];
  }

  /**
   * Builds the line under a chart with a tile value and its change.
   *
   * @param array<string, mixed> $tile
   *   The tile from tile().
   *
   * @return array<string, mixed>
   *   The render array.
   */
  protected function foot(array $tile): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<p class="stats-card__foot stats-foot--{{ tile.key }}">{{ tile.label }}: <b class="stats-foot__value">{{ tile.value }}</b>{% if tile.delta %} <i class="stats-kpi__delta stats-kpi__delta--{{ tile.direction }}">{{ tile.delta }}</i> {{ tile.versus }}{% endif %}</p>',
      '#context' => ['tile' => $tile],
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
