<?php

namespace Drupal\stats\Controller;

use Drupal\Component\Datetime\TimeInterface;
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
      $container->get('datetime.time'),
      $container->get('form_builder'),
    );
  }

  /**
   * Builds the stats page.
   */
  public function page(Request $request): array {

    $timezone = $this->activitySnapshots->timezone();
    $now = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))->setTimezone($timezone);
    $period = StatsPeriod::fromQuery($request->query->all(), $timezone, $now);

    return [
      '#theme' => 'stats_page',
      '#attached' => ['library' => ['stats/charts']],
      '#period_bar' => $this->periodBar($period, $now),
      // The range form belongs to custom periods only.
      '#range_form' => $period->type() === StatsPeriod::CUSTOM ? $this->formBuilder->getForm(StatsRangeForm::class, $period->from()->format('Y-m-d'), $period->to()->modify('-1 day')->format('Y-m-d'), $now->format('Y-m-d')) : [],
      '#sections' => [
        'overview' => $this->overviewSection($period, $now),
        'projects' => $this->projectsSection($period),
        'applications' => $this->applicationsSection($period),
        'creatives' => $this->creativesSection($period),
        'organizations' => $this->organizationsSection($period),
        'feedback' => $this->feedbackSection($period),
      ],
      // The page expires after an hour instead of on every change: the list
      // cache tags of projects, logs and users would invalidate it on almost
      // every request of an active site.
      '#cache' => [
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
   * Builds the overview section: one card per total with its course.
   *
   * Each card shows a small chart of the total at the end of each bucket and
   * the last of those totals as its value. A period that has not ended yet
   * shows the current totals in its current bucket. Its later buckets stay
   * empty, and a period entirely in the future shows the current totals.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function overviewSection(StatsPeriod $period, \DateTimeImmutable $now): array {

    $last_day = $period->to() <= $now ? $period->to()->modify('-1 day') : $now;

    $labels = $period->bucketLabels();
    $registrations = $this->activityStats->registrations($period);
    $states = $this->projectStats->statesAtBucketEnds($period, $now->getTimestamp());
    $cards = [
      'creatives' => [$this->t('Registered creatives'), $registrations['creatives'], 'countCreatives'],
      'organizations' => [$this->t('Registered organizations'), $registrations['organizations'], 'countOrganizations'],
      'open' => [$this->t('Open projects'), $states['open'], 'countOpenProjects'],
      'ongoing' => [$this->t('Ongoing projects'), $states['ongoing'], 'countOngoingProjects'],
      'completed' => [$this->t('Completed projects'), $states['completed'], 'countCompletedProjects'],
      'mediated' => [$this->t('Mediated projects'), $states['mediated'], 'countMediatedProjects'],
    ];

    $overview = [];
    foreach ($cards as $key => [$title, $values, $count]) {
      $values = $this->beforeNow($period, $now, $values);
      $total = $this->last($values) ?? $this->calculator->{$count}();
      $chart = StatsCharts::line('stats-overview-' . $key, $title, $labels, [(string) $title => $values]);
      $overview[$key] = StatsCharts::card($chart, compact: TRUE, value: (string) $total);
    }

    $hint = $this->t('As of @date', ['@date' => $last_day->format('d.m.Y')]);
    return StatsCharts::section('overview', $this->t('Overview'), $overview, ['class' => ['stats-overview']], $hint);
  }

  /**
   * Builds the projects section: flow, cohort funnel and stage medians.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function projectsSection(StatsPeriod $period): array {

    $flow = $this->projectStats->flow($period);
    $transitions = [
      ProjectTransition::Submit->value => $this->t('Submitted'),
      ProjectTransition::Publish->value => $this->t('Published'),
      ProjectTransition::Mediate->value => $this->t('Mediated'),
      ProjectTransition::Complete->value => $this->t('Completed'),
      ProjectTransition::Decline->value => $this->t('Declined'),
    ];
    $series = [];
    foreach ($transitions as $transition => $name) {
      $series[(string) $name] = $flow[$transition];
    }
    $flow_chart = StatsCharts::bar('stats-flow', $this->t('Project flow'), $period->bucketLabels(), $series);

    $cohort = $this->projectStats->cohort($period);
    $cohort_title = $this->t('Cohort: published in @period', ['@period' => $this->label($period)]);
    $cohort_chart = StatsCharts::bar('stats-cohort', $cohort_title, [
      $this->t('Published'),
      $this->t('Mediated'),
      $this->t('Completed'),
    ], [
      (string) $this->t('Projects') => [$cohort['published'], $cohort['mediated'], $cohort['completed']],
    ], horizontal: TRUE);
    $cohort_card = StatsCharts::card($cohort_chart, description: $this->t('Projects first published in the period and how far they got'));
    $cohort_card['foot'] = StatsCharts::foot(
      StatsCharts::tile('mediation-rate', $this->t('Mediation rate'), $cohort['mediation_rate'], $this->t('Mediated ÷ published, without open or externally archived ones'), TRUE),
    );

    $medians = $this->projectStats->stageMedians($period);
    $stages_chart = StatsCharts::bar('stats-stages', $this->t('Median days per stage'), [
      $this->t('Submit → publish'),
      $this->t('Publish → 1st application'),
      $this->t('Publish → mediate'),
      $this->t('Mediate → complete'),
    ], [
      (string) $this->t('Days') => $medians,
    ], horizontal: TRUE);

    return StatsCharts::section('projects', $this->t('Projects'), [
      'flow' => StatsCharts::card($flow_chart, wide: TRUE, tall: TRUE),
      'cohort' => $cohort_card,
      'stages' => StatsCharts::card($stages_chart, description: $this->t('Median days to each step reached in the period')),
    ]);
  }

  /**
   * Builds the applications section: applications, applicants and tiles.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function applicationsSection(StatsPeriod $period): array {

    $applications = $this->activityStats->applications($period);
    $engaged = $this->activityStats->engagedCreatives($period);
    $returned = $this->activityStats->returnAfterRejection($period);

    $chart = StatsCharts::bar('stats-applications', $this->t('Applications and applicants'), $period->bucketLabels(), [
      (string) $this->t('Applications') => $applications['per_bucket'],
      (string) $this->t('Distinct applicants') => $applications['active_per_bucket'],
    ]);
    $tiles = [
      StatsCharts::tile('active-applicants', $this->t('Active applicants'), $applications['active'], $this->t('Creatives who applied at least once in the period')),
      StatsCharts::tile('first-time-applicants', $this->t('First-time applicants'), $applications['first_time'], $this->t('Creatives whose first application falls in the period')),
      StatsCharts::tile('engaged-creatives', $this->t('Engaged'), $engaged, $this->t('Creatives mediated to a project in the period')),
      StatsCharts::tile('came-back', $this->t('Came back'), $returned, $this->t('Applicants not chosen in the period who applied again later'), TRUE),
    ];

    return StatsCharts::section('applications', $this->t('Applications'), [
      'applications' => StatsCharts::card($chart, wide: TRUE),
      'tiles' => StatsCharts::tiles($tiles),
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
    $activation_chart = StatsCharts::bar('stats-activation', $this->t('Registrations and activation'), $labels, [
      (string) $this->t('Registered') => $activation['registered'],
      (string) $this->t('Applied within 30 days') => $activation['activated'],
    ]);
    $activation_card = StatsCharts::card($activation_chart, wide: TRUE);
    $activation_card['foot'] = StatsCharts::foot(
      StatsCharts::tile('activation-rate', $this->t('Activation rate'), $activation['rate'], $this->t('Registered creatives who applied within 30 days'), TRUE),
    );

    $histogram = $this->activityStats->firstApplicationHistogram($period);
    $histogram_chart = StatsCharts::bar('stats-first-application', $this->t('First application after registration'), [
      $this->t('≤ 1 d'),
      $this->t('≤ 7 d'),
      $this->t('≤ 30 d'),
      $this->t('≤ 90 d'),
      $this->t('≤ 1 year'),
      $this->t('Later'),
      $this->t('Never'),
    ], [
      (string) $this->t('Creatives') => $histogram,
    ]);

    $cards = [
      'activation' => $activation_card,
      'first_application' => StatsCharts::card($histogram_chart),
    ];

    // The activity shows only for periods with snapshots. Buckets without a
    // snapshot hold NULL and leave a gap in the line; a snapshot of 0 users
    // is data.
    $activity = $this->activitySnapshots->series($period);
    $creatives = $this->last($activity['creative']);
    $organizations = $this->last($activity['organization']);
    if ($creatives !== NULL || $organizations !== NULL) {
      $activity_chart = StatsCharts::line('stats-activity', $this->t('Active users, last 30 days'), $labels, [
        (string) $this->t('Creatives') => $activity['creative'],
        (string) $this->t('Organizations') => $activity['organization'],
      ]);
      $cards['activity'] = StatsCharts::card($activity_chart, wide: TRUE, description: $this->t('Users who visited in the 30 days up to each point'));
      $cards['tiles'] = StatsCharts::tiles([
        StatsCharts::tile('active-creatives', $this->t('Active creatives'), $creatives, $this->t('Visited in the 30 days up to the last snapshot')),
        StatsCharts::tile('active-organizations', $this->t('Active organizations'), $organizations, $this->t('Visited in the 30 days up to the last snapshot')),
      ]);
    }

    return StatsCharts::section('creatives', $this->t('Creatives'), $cards);
  }

  /**
   * Builds the organizations section: prospect funnel and reasons.
   *
   * @return array<string, mixed>
   *   The section render array.
   */
  protected function organizationsSection(StatsPeriod $period): array {

    $funnel = $this->activityStats->prospectFunnel($period);
    $funnel_chart = StatsCharts::bar('stats-prospects', $this->t('Prospect funnel'), [
      $this->t('Created'),
      $this->t('Managed'),
      $this->t('First project published'),
    ], [
      (string) $this->t('Organizations') => $funnel,
    ], horizontal: TRUE);

    $declines = $this->activityStats->reasons($period, 'project_decline');
    $decline_chart = StatsCharts::doughnut('stats-decline-reasons', $this->t('Decline reasons'), $this->reasonLabels($declines, ProjectDeclineReason::class), $declines);
    $archives = $this->activityStats->reasons($period, 'project_archive');
    $archive_chart = StatsCharts::doughnut('stats-archive-reasons', $this->t('Archive reasons'), $this->reasonLabels($archives, ProjectArchiveReason::class), $archives);

    // The decline reasons span two columns and rows, the prospect funnel and
    // the archive reasons stack beside them.
    return StatsCharts::section('organizations', $this->t('Organizations and reasons'), [
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

    $bundle_titles = [
      'creative' => $this->t('Feedback from creatives'),
      'organization' => $this->t('Feedback from organizations'),
    ];

    $cards = [];
    foreach ($this->feedbackStats->feedback($period) as $bundle => $data) {
      $labels = array_map(fn($label) => preg_replace('/ Scale$/', '', $label), array_values($this->feedbackStats->scaleFields($bundle)));
      $chart = StatsCharts::bar('stats-feedback-' . $bundle, $bundle_titles[$bundle] ?? $bundle, $labels, [
        (string) $this->t('Average (max 5)') => $data['scales'],
      ], horizontal: TRUE, max: 5);
      $card = StatsCharts::card($chart);
      $card['foot'] = StatsCharts::foot(
        StatsCharts::tile('answers-' . $bundle, $this->t('Answers'), $data['count']),
        StatsCharts::tile('recommend-' . $bundle, $this->t('Recommend'), $data['recommend'], rate: TRUE),
      );
      $cards['feedback_' . $bundle] = $card;
    }

    // The two bundles side by side.
    return StatsCharts::section('feedback', $this->t('Feedback'), $cards, ['data-columns' => '2']);
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
   * Gets the last value that is not NULL.
   *
   * @param array<int|string, ?int> $values
   *   The values.
   */
  protected function last(array $values): ?int {
    foreach (array_reverse($values) as $value) {
      if ($value !== NULL) {
        return $value;
      }
    }
    return NULL;
  }

  /**
   * Gets the labels of reasons counted by ActivityStats::reasons().
   *
   * @param array<string, int> $counts
   *   The counts keyed by reason.
   * @param class-string<\Drupal\projects\ProjectDeclineReason|\Drupal\projects\ProjectArchiveReason> $enum
   *   The enum of the reasons.
   *
   * @return array<int, string|\Stringable>
   *   The labels in the order of the counts.
   */
  protected function reasonLabels(array $counts, string $enum): array {
    $labels = [];
    foreach (array_keys($counts) as $reason) {
      $labels[] = $reason === 'unknown' ? $this->t('Unknown') : $enum::tryFrom($reason)?->label() ?? $reason;
    }
    return $labels;
  }

  /**
   * Gets the translated label of a period.
   */
  protected function label(StatsPeriod $period): string|\Stringable {
    return $period->type() === StatsPeriod::ALL ? $this->t('All time') : $period->label();
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

    // All time has no neighbors. A neighbor starting before 2014 or after the
    // current time is shown disabled.
    $steps = [];
    if ($period->type() !== StatsPeriod::ALL) {
      $previous = $period->previous();
      $next = $period->next();
      $steps['previous'] = [
        'label' => $period->hasPrevious() ? $this->t('Previous period: @period', ['@period' => $previous->label()]) : $this->t('No previous period'),
        'url' => $period->hasPrevious() ? $link($previous) : NULL,
      ];
      $steps['next'] = [
        'label' => $next->from() <= $now ? $this->t('Next period: @period', ['@period' => $next->label()]) : $this->t('No next period'),
        'url' => $next->from() <= $now ? $link($next) : NULL,
      ];
    }

    return [
      'heading' => $this->label($period),
      'segments' => $segments,
      'steps' => $steps,
    ];
  }

}
