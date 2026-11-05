<?php

namespace Drupal\stats\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\projects\ProjectArchiveReason;
use Drupal\projects\ProjectDeclineReason;
use Drupal\projects\ProjectTransition;
use Drupal\stats\Activity\ActivitySnapshots;
use Drupal\stats\Form\StatsRangeForm;
use Drupal\stats\Stats\ActivityStats;
use Drupal\stats\Stats\FeedbackStats;
use Drupal\stats\Stats\ProjectStats;
use Drupal\stats\StatsCalculator;
use Drupal\stats\StatsCharts;
use Drupal\stats\StatsPeriod;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the stats page.
 */
class StatsController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs the stats controller.
   */
  public function __construct(
    protected ProjectStats $projectStats,
    protected ActivityStats $activityStats,
    protected FeedbackStats $feedbackStats,
    protected ActivitySnapshots $activitySnapshots,
    protected StatsCalculator $calculator,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Creates the stats controller with its services.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('stats.project_stats'),
      $container->get('stats.activity_stats'),
      $container->get('stats.feedback_stats'),
      $container->get('stats.activity_snapshots'),
      $container->get('stats.calculator'),
      $container->get('config.factory'),
      $container->get('datetime.time'),
      $container->get('form_builder'),
    );
  }

  /**
   * Builds the stats page.
   */
  public function page(Request $request): array {

    $timezone = new \DateTimeZone($this->configFactory->get('system.date')->get('timezone.default'));
    $now = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($timezone);
    $period = StatsPeriod::fromQuery($request->query->all(), $timezone, $now);

    $current = [
      'flow' => $this->projectStats->flow($period),
      'cohort' => $this->projectStats->cohort($period),
      'medians' => $this->projectStats->stageMedians($period),
      'first_application' => $this->activityStats->firstApplicationMedian($period, $this->projectStats->publishRows($period)),
    ];

    return [
      '#theme' => 'stats_page',
      '#attached' => ['library' => ['stats/charts']],
      '#period_bar' => $this->periodBar($period, $now),
      // The range form belongs to custom periods only.
      '#range_form' => $period->type() === StatsPeriod::CUSTOM ? $this->formBuilder->getForm(StatsRangeForm::class, $period->from()->format('Y-m-d'), $period->to()->modify('-1 day')->format('Y-m-d')) : [],
      '#sections' => [
        'overview' => $this->overviewSection($period, $now),
        'projects' => $this->projectsSection($period, $current),
        'applications' => $this->applicationsSection($period),
        'creatives' => $this->creativesSection($period),
        'organizations' => $this->organizationsSection($period),
        'feedback' => $this->feedbackSection($period),
      ],
      '#cache' => [
        'tags' => ['project_list', 'log_list', 'feedback_list', 'user_list'],
        'contexts' => [
          'url.query_args:period',
          'url.query_args:year',
          'url.query_args:quarter',
          'url.query_args:month',
          'url.query_args:from',
          'url.query_args:to',
          'user.permissions',
        ],
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Builds the projects section: flow, cohort funnel and stage medians.
   *
   * @param \Drupal\stats\StatsPeriod $period
   *   The period.
   * @param array<string, mixed> $current
   *   The project stats of the period.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function projectsSection(StatsPeriod $period, array $current): array {

    $labels = $period->bucketLabels();
    $transitions = [
      ProjectTransition::Submit->value => $this->t('Submitted'),
      ProjectTransition::Publish->value => $this->t('Published'),
      ProjectTransition::Mediate->value => $this->t('Mediated'),
      ProjectTransition::Complete->value => $this->t('Completed'),
      ProjectTransition::Decline->value => $this->t('Declined'),
    ];

    $series = [];
    foreach ($transitions as $transition => $name) {
      $series[(string) $name] = array_values($current['flow'][$transition]);
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

    $flow_chart = StatsCharts::bar('stats-flow', (string) $this->t('Project flow'), $labels, $series);
    $funnel_title = (string) $this->t('Cohort: published in @period', ['@period' => $period->label()]);
    $funnel_chart = StatsCharts::bar('stats-funnel', $funnel_title, $funnel_labels, $funnel, horizontal: TRUE);
    $funnel_card = StatsCharts::card($funnel_chart, description: $this->t('Projects first published in the period and how far they got'));
    $rate = $this->tile('mediation-rate', $this->t('Mediation rate'), $cohort['mediation_rate'], 'rate', $this->t('Mediated ÷ published, without open or externally archived ones'));
    $funnel_card['foot'] = $this->foot($rate);
    $stages_title = (string) $this->t('Median days per stage');
    $stages_chart = StatsCharts::bar('stats-stages', $stages_title, $stage_labels, $stages, horizontal: TRUE);
    $stages_card = StatsCharts::card($stages_chart, description: $this->t('Median days to each step reached in the period'));

    $heading = $this->heading($this->t('Projects'));
    return $this->section($heading, [
      'flow' => StatsCharts::card($flow_chart, wide: TRUE, tall: TRUE),
      'funnel' => $funnel_card,
      'stages' => $stages_card,
    ]);
  }

  /**
   * Builds the overview section: one card per total with its course.
   *
   * Each card shows the total at the end of the period over a small chart of
   * the total at the end of each bucket, so the last point is the total. A
   * period that has not ended yet shows the current totals. Its buckets after
   * the current one stay empty.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function overviewSection(StatsPeriod $period, \DateTimeImmutable $now): array {

    $ended = $period->to() <= $now;
    $until = $ended ? $period->to()->getTimestamp() : NULL;
    $last_day = $ended ? $period->to()->modify('-1 day') : $now;

    $labels = $period->bucketLabels();
    $registrations = $this->activityStats->registrations($period);
    $states = $this->projectStats->statesAtBucketEnds($period, $now->getTimestamp());
    $cards = [
      'creatives' => [
        $this->t('Registered creatives'),
        $this->calculator->countCreatives($until),
        $this->beforeNow($period, $now, $registrations['creatives_total']),
      ],
      'organizations' => [
        $this->t('Registered organizations'),
        $this->calculator->countOrganizations($until),
        $this->beforeNow($period, $now, $registrations['organizations_total']),
      ],
      'open' => [
        $this->t('Open projects'),
        $this->calculator->countOpenProjects($until),
        $states['open'],
      ],
      'ongoing' => [
        $this->t('Ongoing projects'),
        $this->calculator->countOngoingProjects($until),
        $states['ongoing'],
      ],
      'completed' => [
        $this->t('Completed projects'),
        $this->calculator->countCompletedProjects($until),
        $states['completed'],
      ],
      'mediated' => [
        $this->t('Mediated projects'),
        $this->calculator->countMediatedProjects($until),
        $states['mediated'],
      ],
    ];

    $section = [
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['stats-section-header']],
        'heading' => $this->heading($this->t('Overview')),
        'hint' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('As of @date', ['@date' => $last_day->format('d.m.Y')]),
          '#attributes' => ['class' => ['stats-section-hint']],
        ],
      ],
    ];
    foreach ($cards as $key => [$title, $total, $values]) {
      $chart = StatsCharts::line('stats-overview-' . $key, (string) $title, $labels, [(string) $title => array_values($values)]);
      // Few ticks keep the small chart readable.
      $chart['#raw_options']['options']['scales']['y']['ticks']['maxTicksLimit'] = 3;
      $chart['#raw_options']['options']['scales']['x']['ticks'] = [
        'maxTicksLimit' => 4,
        'maxRotation' => 0,
        'autoSkipPadding' => 12,
      ];
      $card = StatsCharts::card($chart, compact: TRUE);
      $chart = $card['chart'];
      unset($card['chart']);
      $card['value'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => (string) $total,
        '#attributes' => ['class' => ['stats-card-value'], 'data-value' => $key],
      ];
      // A card without data shows its value only.
      if (!StatsCharts::isEmpty($chart)) {
        $card['chart'] = $chart;
      }
      $section[$key] = $card;
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stats-section', 'stats-overview']],
    ] + $section;
  }

  /**
   * Empties the values of the buckets that start after the current time.
   *
   * @param \Drupal\stats\StatsPeriod $period
   *   The period.
   * @param \DateTimeImmutable $now
   *   The current time.
   * @param array<string, int> $values
   *   The values keyed by bucket.
   *
   * @return array<string, ?int>
   *   The values, NULL after the current time.
   */
  protected function beforeNow(StatsPeriod $period, \DateTimeImmutable $now, array $values): array {
    foreach ($period->buckets() as $bucket => [$start]) {
      if ($start > $now->getTimestamp()) {
        $values[$bucket] = NULL;
      }
    }
    return $values;
  }

  /**
   * Builds the applications section: applications, applicants and tiles.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function applicationsSection(StatsPeriod $period): array {

    $labels = $period->bucketLabels();
    $applications = $this->activityStats->applications($period);
    $engaged = $this->activityStats->engagedCreatives($period);
    $returned = $this->activityStats->returnAfterRejection($period);

    $series = [
      (string) $this->t('Applications') => array_values($applications['per_bucket']),
      (string) $this->t('Distinct applicants') => array_values($applications['active_per_bucket']),
    ];
    $chart = StatsCharts::bar('stats-applications', (string) $this->t('Applications and applicants'), $labels, $series);
    $tiles = [
      $this->tile('active-applicants', $this->t('Active applicants'), $applications['active'], 'count', $this->t('Creatives who applied at least once in the period')),
      $this->tile('first-time-applicants', $this->t('First-time applicants'), $applications['first_time'], 'count', $this->t('Creatives whose first application falls in the period')),
      $this->tile('engaged-creatives', $this->t('Engaged'), $engaged, 'count', $this->t('Creatives mediated to a project in the period')),
      $this->tile('came-back', $this->t('Came back'), $returned, 'rate', $this->t('Applicants not chosen in the period who applied again later')),
    ];

    $heading = $this->heading($this->t('Applications'));
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
  protected function creativesSection(StatsPeriod $period): array {

    $labels = $period->bucketLabels();
    $activation = $this->activityStats->activation($period);
    $series = [
      (string) $this->t('Registered') => array_values($activation['registered']),
      (string) $this->t('Applied within 30 days') => array_values($activation['activated']),
    ];
    $activation_chart = StatsCharts::bar('stats-activation', (string) $this->t('Registrations and activation'), $labels, $series);
    $activation_card = StatsCharts::card($activation_chart, wide: TRUE);
    $rate = $this->tile('activation-rate', $this->t('Activation rate'), $activation['rate'], 'rate', $this->t('Registered creatives who applied within 30 days'));
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

    $cards = [
      'activation' => $activation_card,
      'first_application' => StatsCharts::card($histogram_chart),
    ];

    // The activity shows only for periods with snapshots. Buckets without a
    // snapshot hold NULL and leave a gap in the line; a snapshot of 0 users
    // is data.
    $activity = $this->activitySnapshots->series($period);
    $creatives = array_column($activity['creative'], 'active_30d');
    $organizations = array_column($activity['organization'], 'active_30d');
    if (array_filter([...$creatives, ...$organizations], fn($value) => $value !== NULL)) {
      $activity_series = [
        (string) $this->t('Creatives') => array_values($creatives),
        (string) $this->t('Organizations') => array_values($organizations),
      ];
      $activity_chart = StatsCharts::line('stats-activity', (string) $this->t('Active users, last 30 days'), $labels, $activity_series);
      $cards['activity'] = StatsCharts::card($activity_chart, wide: TRUE, description: $this->t('Users who visited in the 30 days up to each point'));
      $cards['tiles'] = $this->tiles([
        $this->tile('active-creatives', $this->t('Active creatives'), $this->lastActive($activity['creative']), 'count', $this->t('Visited in the 30 days up to the last snapshot')),
        $this->tile('active-organizations', $this->t('Active organizations'), $this->lastActive($activity['organization']), 'count', $this->t('Visited in the 30 days up to the last snapshot')),
      ]);
    }

    $heading = $this->heading($this->t('Creatives'));
    return $this->section($heading, $cards);
  }

  /**
   * Builds the organizations section: prospect funnel and reasons.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function organizationsSection(StatsPeriod $period): array {

    $funnel = $this->activityStats->prospectFunnel($period);
    $funnel_labels = [
      (string) $this->t('Created'),
      (string) $this->t('Managed'),
      (string) $this->t('First project published'),
    ];
    $funnel_chart = StatsCharts::bar('stats-prospects', (string) $this->t('Prospect funnel'), $funnel_labels, [
      (string) $this->t('Organizations') => array_values($funnel),
    ], horizontal: TRUE);

    $declines = $this->activityStats->reasons($period, 'project_decline');
    $decline_labels = array_map(fn($reason) => (string) ($reason === 'unknown' ? $this->t('Unknown') : ProjectDeclineReason::tryFrom($reason)?->label() ?? $reason), array_keys($declines));
    $decline_chart = StatsCharts::doughnut('stats-decline-reasons', (string) $this->t('Decline reasons'), $decline_labels, array_values($declines));
    $archives = $this->activityStats->reasons($period, 'project_archive');
    $archive_labels = array_map(fn($reason) => (string) ($reason === 'unknown' ? $this->t('Unknown') : ProjectArchiveReason::tryFrom($reason)?->label() ?? $reason), array_keys($archives));
    $archive_chart = StatsCharts::doughnut('stats-archive-reasons', (string) $this->t('Archive reasons'), $archive_labels, array_values($archives));

    // The decline reasons span two columns and rows, the prospect funnel and
    // the archive reasons stack beside them.
    $heading = $this->heading($this->t('Organizations and reasons'));
    return $this->section($heading, [
      'decline_reasons' => StatsCharts::card($decline_chart, wide: TRUE, tall: TRUE),
      'prospects' => StatsCharts::card($funnel_chart),
      'archive_reasons' => StatsCharts::card($archive_chart),
    ]);
  }

  /**
   * Builds the feedback section.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function feedbackSection(StatsPeriod $period): array {

    $feedback = $this->feedbackStats->feedback($period);
    $bundle_titles = [
      'creative' => $this->t('Feedback from creatives'),
      'organization' => $this->t('Feedback from organizations'),
    ];

    $cards = [];
    foreach ($feedback as $bundle => $data) {
      $fields = $this->feedbackStats->scaleFields($bundle);
      $series = [
        (string) $this->t('Average (max 5)') => array_values($data['scales']),
      ];
      $title = (string) ($bundle_titles[$bundle] ?? $bundle);
      $labels = array_map(fn($label) => preg_replace('/ Scale$/', '', $label), array_values($fields));
      $chart = StatsCharts::bar('stats-feedback-' . $bundle, $title, $labels, $series, horizontal: TRUE, max: 5);
      $card = StatsCharts::card($chart);
      $card['foot'] = $this->foot(
        $this->tile('answers-' . $bundle, $this->t('Answers'), $data['count'], 'count'),
        $this->tile('recommend-' . $bundle, $this->t('Recommend'), $data['recommend'], 'rate'),
      );
      $cards['feedback_' . $bundle] = $card;
    }

    // The two bundles side by side.
    $heading = $this->heading($this->t('Feedback'));
    return $this->section($heading, $cards, ['data-columns' => '2']);
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
   * @param array<string, string> $attributes
   *   More attributes of the section.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function section(array $heading, array $cards, array $attributes = []): array {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['stats-section']] + $attributes,
      'heading' => $heading,
    ] + $cards;
  }

  /**
   * Builds a section heading.
   *
   * @return array<string, mixed>
   *   The heading render array.
   */
  protected function heading(mixed $title): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $title,
      '#attributes' => ['class' => ['stats-section-title']],
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
  protected function foot(array ...$tiles): array {
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
   * @param mixed $label
   *   The label.
   * @param int|float|null $value
   *   The value, NULL if undefined.
   * @param string $type
   *   One of count, rate (a fraction) and days.
   * @param mixed $description
   *   A short line that explains the value, if any.
   *
   * @return array<string, mixed>
   *   The tile.
   */
  protected function tile(string $key, mixed $label, int|float|null $value, string $type, mixed $description = NULL): array {
    $text = '–';
    if ($value !== NULL) {
      $text = match ($type) {
        'count' => (string) $value,
        'rate' => round($value * 100) . ' %',
        default => number_format($value, 1, '.', '') . ' d',
      };
    }
    return [
      'key' => $key,
      'label' => $label,
      'value' => $text,
      'description' => $description,
    ];
  }

  /**
   * Builds the period bar: the type segments and the stepper.
   *
   * @return array<string, mixed>
   *   The variables of the bar.
   */
  protected function periodBar(StatsPeriod $period, \DateTimeImmutable $now): array {

    $link = fn(StatsPeriod $target): string => Url::fromRoute('stats.overview', [], ['query' => $target->query()])->toString();

    $segments = [];
    $labels = [
      StatsPeriod::ALL => $this->t('All time'),
      StatsPeriod::YEAR => $this->t('Year'),
      StatsPeriod::QUARTER => $this->t('Quarter'),
      StatsPeriod::MONTH => $this->t('Month'),
      StatsPeriod::CUSTOM => $this->t('Custom'),
    ];
    foreach ($labels as $type => $label) {
      $segments[] = [
        'type' => $type,
        'label' => $label,
        'url' => $link($period->withGranularity($type, $now)),
        'active' => $period->type() === $type,
      ];
    }

    // All time has no neighbors. A neighbor beyond the current time is shown
    // disabled.
    $steps = [];
    if ($period->type() !== StatsPeriod::ALL) {
      $previous = $period->previous();
      $next = $period->next();
      $steps['previous'] = [
        'label' => $this->t('Previous period: @period', ['@period' => $previous->label()]),
        'url' => $link($previous),
      ];
      $steps['next'] = [
        'label' => $next->from() <= $now ? $this->t('Next period: @period', ['@period' => $next->label()]) : $this->t('No next period'),
        'url' => $next->from() <= $now ? $link($next) : NULL,
      ];
    }

    return [
      'heading' => $period->label(),
      'segments' => $segments,
      'steps' => $steps,
    ];
  }

}
