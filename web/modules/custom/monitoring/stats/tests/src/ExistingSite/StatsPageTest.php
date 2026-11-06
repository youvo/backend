<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\projects\Entity\Project;
use Drupal\stats\Activity\ActivitySnapshots;
use Drupal\stats\Controller\StatsController;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the stats page with the period bar and the overview cards.
 */
#[Group('stats')]
class StatsPageTest extends StatsTestBase {

  /**
   * The period bar switches the type and steps between periods.
   */
  public function testPeriodBar(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2018]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextEquals('css', '.stats-stepper .stats-period-heading', '2018');

    // The segments switch the type around the period being viewed.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2024]]);
    $expected = [
      'all' => ['period' => 'all'],
      'year' => ['period' => 'year', 'year' => '2024'],
      'quarter' => ['period' => 'quarter', 'year' => '2024', 'quarter' => '4'],
      'month' => ['period' => 'month', 'year' => '2024', 'month' => '12'],
      'custom' => ['period' => 'custom', 'from' => '2024-01-01', 'to' => '2024-12-31'],
    ];
    foreach ($expected as $type => $query) {
      $segment = $this->assertSession()->elementExists('css', '.stats-segments a.stats-segment[data-segment="' . $type . '"]');
      $this->assertSame($query, $this->linkQuery($segment->getAttribute('href')), $type);
      $this->assertSame($type === 'year' ? 'page' : NULL, $segment->getAttribute('aria-current'), $type);
    }

    // The stepper links the neighbors by their labels.
    $previous = $this->assertSession()->elementExists('css', '.stats-stepper a[data-step="previous"][rel="prev"]');
    $this->assertSame('Previous period: 2023', $previous->getAttribute('aria-label'));
    $next = $this->assertSession()->elementExists('css', '.stats-stepper a[data-step="next"][rel="next"]');
    $this->assertSame('Next period: 2025', $next->getAttribute('aria-label'));
    $next->click();
    $this->assertSession()->elementTextEquals('css', '.stats-period-heading', '2025');

    // The current period has a disabled next step without a target.
    $this->drupalGet('stats', ['query' => ['period' => 'quarter']]);
    $now = new \DateTimeImmutable('now', $this->timezone());
    $label = 'Q' . (intdiv((int) $now->format('n') - 1, 3) + 1) . ' ' . $now->format('Y');
    $this->assertSession()->elementTextEquals('css', '.stats-stepper .stats-period-heading', $label);
    $disabled = $this->assertSession()->elementExists('css', '.stats-stepper [data-step="next"][aria-disabled="true"]');
    $this->assertFalse($disabled->hasAttribute('href'));
    $this->assertSession()->elementExists('css', '.stats-stepper a[data-step="previous"][href]');
    // Its custom range ends today, the latest day of the range form.
    $custom = $this->assertSession()->elementExists('css', 'a.stats-segment[data-segment="custom"]');
    $this->assertSame($now->format('Y-m-d'), $this->linkQuery($custom->getAttribute('href'))['to']);

    // The first periods of 2014 have a disabled previous step.
    $first = [
      ['period' => 'year', 'year' => 2014],
      ['period' => 'quarter', 'year' => 2014, 'quarter' => 1],
      ['period' => 'month', 'year' => 2014, 'month' => 1],
      ['period' => 'custom', 'from' => '2014-01-01', 'to' => '2014-01-10'],
    ];
    foreach ($first as $query) {
      $this->drupalGet('stats', ['query' => $query]);
      $disabled = $this->assertSession()->elementExists('css', '.stats-stepper [data-step="previous"][aria-disabled="true"]');
      $this->assertFalse($disabled->hasAttribute('href'), json_encode($query));
      $this->assertSame('No previous period', $disabled->getAttribute('aria-label'));
    }
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2015]]);
    $this->assertSession()->elementExists('css', '.stats-stepper a[data-step="previous"][href]');

    // Years before 2014 or after the current one fall back to all time.
    foreach ([2013, (int) $now->format('Y') + 1] as $year) {
      $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => $year]]);
      $this->assertSession()->elementTextEquals('css', '.stats-period-heading', 'All time');
    }

    // All time has no steps.
    $this->drupalGet('stats');
    $this->assertSession()->elementNotExists('css', '.stats-step');
    $this->assertSession()->elementExists('css', 'a.stats-segment[data-segment="all"][aria-current="page"]');
  }

  /**
   * Periods overlapping 2014 or 2015 note their imprecise data, all time not.
   */
  public function testImpreciseDataWarning(): void {

    $this->drupalLogin($this->createCreative('creative', 'manager'));
    $warning = '.stats-period .stats-note';
    $text = 'The data for 2014 and 2015 may be imprecise.';
    $shown = [
      ['period' => 'year', 'year' => 2015],
      ['period' => 'custom', 'from' => '2015-12-20', 'to' => '2016-01-10'],
    ];
    foreach ($shown as $query) {
      $this->drupalGet('stats', ['query' => $query]);
      $this->assertSession()->elementTextContains('css', $warning, $text);
      $this->assertSession()->elementNotExists('css', '.stats-page .messages, .stats-note button');
    }
    $hidden = [
      ['period' => 'year', 'year' => 2016],
      ['period' => 'all'],
    ];
    foreach ($hidden as $query) {
      $this->drupalGet('stats', ['query' => $query]);
      $this->assertSession()->elementNotExists('css', $warning);
      $this->assertSession()->pageTextNotContains($text);
    }
  }

  /**
   * Gets the query arguments of a link.
   *
   * @return array<string, string>
   *   The arguments.
   */
  protected function linkQuery(string $href): array {
    $query = [];
    parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
    return $query;
  }

  /**
   * Gets the values of a series from the hidden table of a chart.
   *
   * @return string[]
   *   The values, one per row.
   */
  protected function chartColumn(string $id, string $series): array {
    $table = $this->assertSession()->elementExists('xpath', '//div[@id="' . $id . '"]/following-sibling::div//table');
    $headers = array_map(fn($cell) => trim($cell->getText()), $table->findAll('css', 'thead th'));
    $column = array_search($series, $headers, TRUE);
    $this->assertIsInt($column, $series);
    $values = [];
    foreach ($table->findAll('css', 'tbody tr') as $row) {
      $values[] = trim($row->findAll('xpath', 'th|td')[$column]->getText());
    }
    return $values;
  }

  /**
   * The overview cards show the totals at the end of the period.
   */
  public function testOverviewAndMediationRate(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $calculator = $this->container->get('stats.calculator');
    $cards = [
      'creatives' => ['Registered creatives', 'countCreatives'],
      'organizations' => ['Registered organizations', 'countOrganizations'],
      'open' => ['Open projects', 'countOpenProjects'],
      'ongoing' => ['Ongoing projects', 'countOngoingProjects'],
      'completed' => ['Completed projects', 'countCompletedProjects'],
      'mediated' => ['Mediated projects', 'countMediatedProjects'],
    ];

    // Without a period, the page starts on all time and its totals equal the
    // calculator without a timestamp.
    $this->drupalGet('stats');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextEquals('css', '.stats-period-heading', 'All time');
    $this->assertSession()->elementTextEquals('css', '.stats-overview .stats-section-title', 'Overview');
    $today = (new \DateTimeImmutable('now', $this->timezone()))->format('d.m.Y');
    $this->assertSession()->elementTextContains('css', '.stats-overview .stats-section-hint', 'As of ' . $today);
    $this->assertCount(6, $this->getSession()->getPage()->findAll('css', '.stats-overview > .stats-card'));
    foreach ($cards as $key => [$title, $method]) {
      $id = 'stats-overview-' . $key;
      $card = '.stats-overview .stats-card[aria-labelledby="' . $id . '-title"]';
      $this->assertSession()->elementExists('css', $card . ' #' . $id);
      $this->assertSession()->elementTextEquals('css', $card . ' .stats-card-title', $title);
      $this->assertSession()->elementTextEquals('css', $card . ' .stats-card-value', (string) $calculator->{$method}());
    }
    $this->assertSession()->elementExists('xpath', '//a[normalize-space()="All time"][@aria-current="page"]');

    // A past period shows the state at its end, 2018-12-31 included, the
    // last point of each chart. The scenario registers no creatives and three
    // organizations, and the site holds no older users or projects.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2018]]);
    $this->assertSession()->elementTextContains('css', '.stats-overview .stats-section-hint', 'As of 31.12.2018');
    $expected = [
      'creatives' => '0',
      'organizations' => '3',
      'open' => '4',
      'ongoing' => '2',
      'completed' => '1',
      'mediated' => '3',
    ];
    foreach ($expected as $key => $value) {
      $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-overview-' . $key . '-title"] .stats-card-value', $value);
      // The creatives have no data in this year and show no chart.
      if ($key === 'creatives') {
        $this->assertSession()->elementNotExists('css', '#stats-overview-creatives');
        continue;
      }
      $points = $this->chartColumn('stats-overview-' . $key, $cards[$key][0]);
      $this->assertSame($value, end($points), $key);
    }

    // The running year ends today.
    $year = (new \DateTimeImmutable('now', $this->timezone()))->format('Y');
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => $year]]);
    $this->assertSession()->elementTextContains('css', '.stats-overview .stats-section-hint', 'As of ' . $today);
    foreach (array_keys($cards) as $key) {
      $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-overview-' . $key . '-title"] .stats-card-value', (string) $calculator->{$cards[$key][1]}());
    }

    // A period without data hides the overview charts, not the values.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2019]]);
    $this->assertSession()->elementExists('css', '.stats-card[aria-labelledby="stats-overview-creatives-title"] .stats-card-value');
    $this->assertSession()->elementNotExists('css', '#stats-overview-creatives');

    // The mediation rate of the cohort moved to the footnote of its card.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2018]]);
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="stats-cohort"]/ancestor::*[contains(@class, "stats-card")][1]//*[@data-foot="mediation-rate"]', 'Mediation rate: 75 %');
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2019]]);
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="stats-cohort"]/ancestor::*[contains(@class, "stats-card")][1]//*[@data-foot="mediation-rate"]', 'Mediation rate: –');
  }

  /**
   * The range form of custom periods selects a custom period.
   */
  public function testRangeForm(): void {

    $this->drupalLogin($this->createCreative('creative', 'manager'));

    // Presets have no range form.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2018]]);
    $this->assertSession()->elementNotExists('css', '.stats-range-form');
    $this->drupalGet('stats');
    $this->assertSession()->elementNotExists('css', '.stats-range-form');

    // Gin styles the date fields and the primary button of the native form,
    // without required markers.
    $this->drupalGet('stats', ['query' => ['period' => 'custom', 'from' => '2018-01-01', 'to' => '2018-12-31']]);
    $this->assertSession()->elementTextEquals('css', '.stats-period-heading', '01.01.2018 – 31.12.2018');
    $this->assertSession()->elementExists('css', 'a.stats-segment[data-segment="custom"][aria-current="page"]');
    $from = $this->assertSession()->elementExists('css', '.stats-range-form input[name="from"].form-element--type-date');
    $this->assertSame('2018-01-01', $from->getValue());
    $to = $this->assertSession()->elementExists('css', '.stats-range-form input[name="to"].form-element--type-date');
    $this->assertSame('2018-12-31', $to->getValue());
    $this->assertSession()->elementExists('css', '.stats-range-form input.button--primary[value="Apply"]');
    $this->assertSession()->elementNotExists('css', '.stats-range-form [required], .stats-range-form .form-required');

    $this->submitForm(['from' => '2018-03-05', 'to' => '2018-04-20'], 'Apply');
    $this->assertSame(['period' => 'custom', 'from' => '2018-03-05', 'to' => '2018-04-20'], $this->linkQuery($this->getUrl()));
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '05.03.2018 – 20.04.2018');
    $previous = $this->assertSession()->elementExists('css', '.stats-stepper a[data-step="previous"]');
    $this->assertSame('Previous period: 17.01.2018 – 04.03.2018', $previous->getAttribute('aria-label'));
    $this->assertSame('2018-03-05', $this->assertSession()->fieldExists('from')->getValue());

    // A range covering a calendar quarter stays custom, so the form stays.
    $this->submitForm(['from' => '2018-04-01', 'to' => '2018-06-30'], 'Apply');
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '01.04.2018 – 30.06.2018');
    $this->assertSession()->elementExists('css', '.stats-range-form');

    // Missing days and a range ending before it starts stay on the form.
    $this->submitForm(['from' => '', 'to' => ''], 'Apply');
    $this->assertSession()->pageTextContains('Enter a start date.');
    $this->assertSession()->pageTextContains('Enter an end date.');
    $this->submitForm(['from' => '2018-05-01', 'to' => '2018-04-01'], 'Apply');
    $this->assertSession()->pageTextContains('The end date must not be before the start date.');
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '01.04.2018 – 30.06.2018');

    // The days lie between 2014 and today.
    $today = (new \DateTimeImmutable('now', $this->timezone()))->format('Y-m-d');
    foreach (['from', 'to'] as $name) {
      $field = $this->assertSession()->fieldExists($name);
      $this->assertSame('2014-01-01', $field->getAttribute('min'));
      $this->assertSame($today, $field->getAttribute('max'));
    }
    $this->submitForm(['from' => '2013-12-31', 'to' => '2014-01-10'], 'Apply');
    $this->assertSession()->pageTextContains('The start date must not be before 01.01.2014, as there is no data before.');
    // A range running past today, as the next step of a custom range, shows
    // today in the form. The range ends on the last day of the current year,
    // as a later year is no valid range, and so is today on 31 December.
    $tomorrow = (new \DateTimeImmutable('tomorrow', $this->timezone()))->format('Y-m-d');
    $year = substr($today, 0, 4);
    $query = ['period' => 'custom', 'from' => $year . '-01-01', 'to' => $year . '-12-31'];
    $this->drupalGet('stats', ['query' => $query]);
    $this->assertSame($today, $this->assertSession()->fieldExists('to')->getValue());
    $this->submitForm(['from' => '2014-01-01', 'to' => $tomorrow], 'Apply');
    $this->assertSession()->pageTextContains('The end date must not be after today.');
  }

  /**
   * The projects section renders its charts with local Chart.js and tables.
   */
  public function testProjectChartsRender(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2018]]);
    $this->assertSession()->statusCodeEquals(200);
    foreach (['stats-flow', 'stats-cohort', 'stats-stages'] as $id) {
      $this->assertSession()->elementExists('css', '#' . $id);
    }
    // The card labels the chart, which draws no title of its own but keeps it
    // for the figure label and the caption of its table.
    $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-flow-title"] h3#stats-flow-title', 'Project flow');
    $chart = $this->assertSession()->elementExists('css', '#stats-flow');
    $definition = json_decode($chart->getAttribute('data-chart'), TRUE);
    $this->assertFalse($definition['options']['plugins']['title']['display']);
    $this->assertSession()->elementExists('css', '.charts-figure[aria-label="Project flow"] #stats-flow');
    $this->assertSession()->elementTextEquals('css', '.charts-figure[aria-label="Project flow"] table caption', 'Data table for Project flow');
    // The colors come from the theme, the month labels are short.
    $this->assertArrayNotHasKey('backgroundColor', $definition['data']['datasets'][0]);
    // The declines are grey, the other series take the palette.
    $grey = array_column(array_filter($definition['data']['datasets'], fn($dataset) => ($dataset['statsColor'] ?? NULL) === 'grey'), 'label');
    $this->assertSame(['Declined'], array_values($grey));
    $this->assertSame(['Jan', 'Feb', 'Mar'], array_slice($definition['data']['labels'], 0, 3));

    // The tables are hidden for screen readers, without a button to show them.
    $this->assertSession()->elementNotExists('css', '.charts-accordion-trigger');
    $html = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('/libraries/chart.js/dist/chart.umd.js', $html);
    $this->assertStringNotContainsString('unpkg.com', $html);

    // The hidden table has one row per month and one column per series.
    $this->assertSession()->elementExists('xpath', '//div[@id="stats-flow"]/following-sibling::div//div[contains(@class, "visually-hidden")]//table');
    $this->assertSame(['1', '1', '2', '1', '1', '0', '1', '0', '0', '0', '0', '0'], $this->chartColumn('stats-flow', 'Published'));

    // The cohort card explains itself in a line under its title.
    $this->assertSession()->elementTextEquals('css', '.stats-card[aria-describedby="stats-cohort-description"] p#stats-cohort-description', 'Projects first published in the period and how far they got');

    // A month counts by week.
    $this->drupalGet('stats', ['query' => ['period' => 'month', 'year' => 2018, 'month' => 11]]);
    $definition = json_decode($this->assertSession()->elementExists('css', '#stats-flow')->getAttribute('data-chart'), TRUE);
    $this->assertSame(['W44', 'W45', 'W46', 'W47', 'W48'], $definition['data']['labels']);
  }

  /**
   * The page render array bubbles its cache metadata to the response.
   */
  public function testCacheMetadata(): void {

    $this->drupalLogin($this->createCreative('creative', 'manager'));
    $page = StatsController::create($this->container)->page(new Request(['period' => 'all']));
    $this->container->get('renderer')->renderInIsolation($page);
    $bubbled = BubbleableMetadata::createFromRenderArray($page);

    $contexts = [
      'url.query_args:period',
      'url.query_args:year',
      'url.query_args:quarter',
      'url.query_args:month',
      'url.query_args:from',
      'url.query_args:to',
      'user.permissions',
    ];
    foreach ($contexts as $context) {
      $this->assertContains($context, $bubbled->getCacheContexts());
    }
    $this->assertSame(3600, $bubbled->getCacheMaxAge());
    // The page expires by time, not by the changes of the lists.
    foreach (['project_list', 'log_list', 'feedback_list', 'user_list'] as $tag) {
      $this->assertNotContains($tag, $bubbled->getCacheTags());
    }
  }

  /**
   * Bad query arguments fall back to all time.
   */
  public function testBadQueryArgumentsRender200(): void {

    $this->drupalLogin($this->createCreative('creative', 'manager'));
    $queries = [
      ['from' => 'foo'],
      ['from' => '2026-05-01', 'to' => '2026-04-01'],
      ['period' => 'decade'],
    ];
    foreach ($queries as $query) {
      $this->drupalGet('stats', ['query' => $query]);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->elementTextEquals('css', '.stats-period-heading', 'All time');
    }
  }

  /**
   * The activity chart shows the active users per bucket.
   */
  public function testActivityChart(): void {

    $rows = [
      '2018-01-15' => [3, 10, 20, 20],
      '2018-01-31' => [5, 12, 30, 30],
      '2018-02-10' => [2, 9, 15, 40],
    ];
    foreach ($rows as $day => $counts) {
      $this->touchDay($day);
      $this->container->get('stats.activity_snapshots')->write($day, 'creative', array_combine(ActivitySnapshots::COLUMNS, $counts));
    }
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    // A year shows the active users per month.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2018]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-activity-title"] .stats-card-title', 'Active users per month');
    $this->assertSame(['30', '15', ''], array_slice($this->chartColumn('stats-activity', 'Creatives'), 0, 3));

    // The tiles show the last bucket with a snapshot and name it.
    $tile = '.stats-tile[data-tile="active-creatives"]';
    $this->assertSession()->elementTextEquals('css', $tile . ' .stats-tile-label', 'Active creatives');
    // A past bucket is complete: no dashed segment.
    $definition = json_decode($this->assertSession()->elementExists('css', '#stats-activity')->getAttribute('data-chart'), TRUE);
    $this->assertArrayNotHasKey('statsRunningIndex', $definition['data']['datasets'][0]);
    $this->assertSession()->elementTextEquals('css', $tile . ' .stats-tile-value', '15');
    $this->assertSession()->elementTextEquals('css', $tile . ' .stats-tile-description', 'Visited in Feb 2018');
    $this->assertSession()->elementExists('css', '.stats-tile[data-tile="active-organizations"]');

    // A quarter shows weeks, a short range days.
    $this->drupalGet('stats', ['query' => ['period' => 'quarter', 'year' => 2018, 'quarter' => 1]]);
    $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-activity-title"] .stats-card-title', 'Active users per week');
    $this->assertSession()->elementTextEquals('css', $tile . ' .stats-tile-value', '9');
    $this->assertSession()->elementTextEquals('css', $tile . ' .stats-tile-description', 'Visited in W06');
    $this->drupalGet('stats', ['query' => ['period' => 'custom', 'from' => '2018-01-10', 'to' => '2018-02-09']]);
    $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-activity-title"] .stats-card-title', 'Active users per day');
    $this->assertSession()->elementTextEquals('css', $tile . ' .stats-tile-value', '5');
    $this->assertSession()->elementTextEquals('css', $tile . ' .stats-tile-description', 'Visited on 31.01.');
  }

  /**
   * The running bucket of the activity chart is marked as incomplete.
   */
  public function testActivityRunningBucket(): void {

    $now = new \DateTimeImmutable('now', $this->timezone());
    $today = $now->format('Y-m-d');
    $this->touchDay($today);
    $counts = array_combine(ActivitySnapshots::COLUMNS, [1, 2, 3, 4]);
    $this->container->get('stats.activity_snapshots')->write($today, 'creative', $counts);
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => $now->format('Y')]]);
    $definition = json_decode($this->assertSession()->elementExists('css', '#stats-activity')->getAttribute('data-chart'), TRUE);
    $this->assertSame((int) $now->format('n') - 1, $definition['data']['datasets'][0]['statsRunningIndex']);
    $this->assertSession()->elementTextEquals('css', '.stats-tile[data-tile="active-creatives"] .stats-tile-description', 'Visited in ' . $now->format('M Y') . ' so far');
  }

  /**
   * A period without snapshots hides the activity chart and its tiles.
   */
  public function testActivityHiddenWithoutSnapshots(): void {

    // Remove the snapshots of 2018, which tearDown() restores.
    $database = $this->container->get('database');
    $days = $database->select('stats_activity', 'a')
      ->fields('a', ['day'])
      ->condition('day', ['2018-01-01', '2018-12-31'], 'BETWEEN')
      ->distinct()
      ->execute()
      ->fetchCol();
    foreach ($days as $day) {
      $this->touchDay($day);
    }
    $database->delete('stats_activity')->condition('day', ['2018-01-01', '2018-12-31'], 'BETWEEN')->execute();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2018]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '#stats-activation');
    $this->assertSession()->elementNotExists('css', '#stats-activity');
    $this->assertSession()->elementNotExists('css', '[data-tile="active-creatives"], [data-tile="active-organizations"]');
    $this->assertSession()->pageTextNotContains('Active users per month');
  }

  /**
   * The feedback section renders its charts.
   */
  public function testFeedbackSectionRenders(): void {

    $this->setUpScenario();
    $project = Project::load(reset($this->projectIds));
    $this->createFeedback('creative', $project, '2018-03-05', ['field_recommend_boolean' => 1]);
    $this->createFeedback('creative', $project, '2018-03-10', ['field_recommend_boolean' => 0]);
    $this->createFeedback('organization', $project, '2018-03-12', ['field_recommend_boolean' => 1]);
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2018]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('xpath', '//h2[contains(@class, "stats-section-title")][normalize-space()="Feedback"]');
    // The answers and the recommend share sit in the footnote, not the title.
    $footers = [
      'creative' => ['Feedback from creatives', 'Answers: 2', 'Recommend: 50 %'],
      'organization' => ['Feedback from organizations', 'Answers: 1', 'Recommend: 100 %'],
    ];
    foreach ($footers as $bundle => [$title, $answers, $recommend]) {
      $card = '.stats-card[aria-labelledby="stats-feedback-' . $bundle . '-title"]';
      $this->assertSession()->elementTextEquals('css', $card . ' .stats-card-title', $title);
      $this->assertSession()->elementTextEquals('css', $card . ' .stats-card-foot [data-foot="answers-' . $bundle . '"]', $answers);
      $this->assertSession()->elementTextEquals('css', $card . ' .stats-card-foot [data-foot="recommend-' . $bundle . '"]', $recommend);
    }
    foreach (['stats-feedback-creative', 'stats-feedback-organization'] as $id) {
      $this->assertSession()->elementExists('css', '#' . $id);
    }
    // The averages run on a fixed axis from 0 to 5.
    $chart = $this->assertSession()->elementExists('css', '#stats-feedback-creative');
    $scale = json_decode($chart->getAttribute('data-chart'), TRUE)['options']['scales']['x'];
    $this->assertSame([0, 5], [$scale['min'], $scale['max']]);
  }

}
