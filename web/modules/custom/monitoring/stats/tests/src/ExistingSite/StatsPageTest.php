<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\projects\Entity\Project;
use Drupal\stats\Controller\StatsController;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the stats page with the period bar and the overview cards.
 */
#[Group('stats')]
class StatsPageTest extends StatsTestBase {

  /**
   * The activity rows the test replaced, keyed by day, to restore them.
   *
   * @var array<string, array<int, array<string, mixed>>>
   */
  protected array $touchedDays = [];

  /**
   * Restores the activity rows the test replaced.
   */
  protected function tearDown(): void {
    $database = $this->container->get('database');
    foreach ($this->touchedDays as $day => $rows) {
      $database->delete('stats_activity')->condition('day', $day)->execute();
      foreach ($rows as $row) {
        $database->insert('stats_activity')->fields($row)->execute();
      }
    }
    parent::tearDown();
  }

  /**
   * The period bar switches the type and steps between periods.
   */
  public function testPeriodBar(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextEquals('css', '.stats-stepper .stats-period-heading', '2001');
    $this->assertSession()->pageTextNotContains('vs ');
    $this->assertSession()->fieldNotExists('compare');
    $this->assertSession()->fieldNotExists('manager');

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

    // All time has no steps.
    $this->drupalGet('stats');
    $this->assertSession()->elementNotExists('css', '.stats-step');
    $this->assertSession()->elementExists('css', 'a.stats-segment[data-segment="all"][aria-current="page"]');
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

    // The totals and growth sections are gone.
    $this->assertSession()->elementNotExists('css', '.stats-totals');
    $this->assertSession()->elementNotExists('css', '.stats-growth');
    $this->assertSession()->elementNotExists('css', '[id^="stats-growth-"]');
    $this->assertSession()->elementNotExists('xpath', '//h2[normalize-space()="Totals" or normalize-space()="Growth"]');

    // A past period shows the state at its end, 2001-12-31 included.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->elementTextContains('css', '.stats-overview .stats-section-hint', 'As of 31.12.2001');
    $expected = ['open' => 4, 'ongoing' => 2, 'completed' => 1, 'mediated' => 3, 'organizations' => 3];
    foreach ($expected as $key => $value) {
      $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-overview-' . $key . '-title"] .stats-card-value', (string) $value);
    }

    // A period in the future ends today.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2090]]);
    $this->assertSession()->elementTextContains('css', '.stats-overview .stats-section-hint', 'As of ' . $today);
    foreach (array_keys($cards) as $key) {
      $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-overview-' . $key . '-title"] .stats-card-value', (string) $calculator->{$cards[$key][1]}());
    }

    // A period without data hides the overview charts, not the values.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2002]]);
    $this->assertSession()->elementExists('css', '.stats-card[aria-labelledby="stats-overview-creatives-title"] .stats-card-value');
    $this->assertSession()->elementNotExists('css', '#stats-overview-creatives');

    // The mediation rate of the cohort moved to the footnote of its card.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="stats-cohort"]/ancestor::*[contains(@class, "stats-card")][1]//*[@data-foot="mediation-rate"]', 'Mediation rate: 75 %');
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2002]]);
    $this->assertSession()->elementTextEquals('xpath', '//*[@id="stats-cohort"]/ancestor::*[contains(@class, "stats-card")][1]//*[@data-foot="mediation-rate"]', 'Mediation rate: –');
  }

  /**
   * The range form of custom periods selects a custom period.
   */
  public function testRangeForm(): void {

    $this->drupalLogin($this->createCreative('creative', 'manager'));

    // Presets have no range form.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->elementNotExists('css', '.stats-range-form');
    $this->drupalGet('stats');
    $this->assertSession()->elementNotExists('css', '.stats-range-form');

    // Gin styles the date fields and the primary button of the native form,
    // without required markers.
    $this->drupalGet('stats', ['query' => ['period' => 'custom', 'from' => '2001-01-01', 'to' => '2001-12-31']]);
    $this->assertSession()->elementTextEquals('css', '.stats-period-heading', '01.01.2001 – 31.12.2001');
    $this->assertSession()->elementExists('css', 'a.stats-segment[data-segment="custom"][aria-current="page"]');
    $from = $this->assertSession()->elementExists('css', '.stats-range-form input[name="from"].form-element--type-date');
    $this->assertSame('2001-01-01', $from->getValue());
    $to = $this->assertSession()->elementExists('css', '.stats-range-form input[name="to"].form-element--type-date');
    $this->assertSame('2001-12-31', $to->getValue());
    $this->assertSession()->elementExists('css', '.stats-range-form input.button--primary[value="Apply"]');
    $this->assertSession()->elementNotExists('css', '.stats-range-form [required], .stats-range-form .form-required');

    $this->submitForm(['from' => '2001-03-05', 'to' => '2001-04-20'], 'Apply');
    $this->assertSame(['period' => 'custom', 'from' => '2001-03-05', 'to' => '2001-04-20'], $this->linkQuery($this->getUrl()));
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '05.03.2001 – 20.04.2001');
    $previous = $this->assertSession()->elementExists('css', '.stats-stepper a[data-step="previous"]');
    $this->assertSame('Previous period: 17.01.2001 – 04.03.2001', $previous->getAttribute('aria-label'));
    $this->assertSame('2001-03-05', $this->assertSession()->fieldExists('from')->getValue());

    // A range covering a calendar quarter stays custom, so the form stays.
    $this->submitForm(['from' => '2001-04-01', 'to' => '2001-06-30'], 'Apply');
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '01.04.2001 – 30.06.2001');
    $this->assertSession()->elementExists('css', '.stats-range-form');

    // Missing days and a range ending before it starts stay on the form.
    $this->submitForm(['from' => '', 'to' => ''], 'Apply');
    $this->assertSession()->pageTextContains('Enter a start date.');
    $this->assertSession()->pageTextContains('Enter an end date.');
    $this->submitForm(['from' => '2001-05-01', 'to' => '2001-04-01'], 'Apply');
    $this->assertSession()->pageTextContains('The end date must not be before the start date.');
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '01.04.2001 – 30.06.2001');
  }

  /**
   * The projects section renders its charts with local Chart.js and tables.
   */
  public function testProjectChartsRender(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
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
    $this->assertSame(['Jan', 'Feb', 'Mar'], array_slice($definition['data']['labels'], 0, 3));

    // The tables are hidden for screen readers, without a button to show them.
    $this->assertSession()->elementNotExists('css', '.charts-accordion-trigger');
    $html = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('/libraries/chart.js/dist/chart.umd.js', $html);
    $this->assertStringNotContainsString('unpkg.com', $html);

    // The table has one row per month and one column per series.
    $table = $this->assertSession()->elementExists('xpath', '//div[@id="stats-flow"]/following-sibling::div//div[contains(@class, "visually-hidden")]//table');
    $headers = array_map(fn($cell) => trim($cell->getText()), $table->findAll('css', 'thead th'));
    $column = array_search('Published', $headers, TRUE);
    $this->assertIsInt($column);
    $values = [];
    foreach ($table->findAll('css', 'tbody tr') as $row) {
      $cells = $row->findAll('xpath', 'th|td');
      $values[] = trim($cells[$column]->getText());
    }
    $this->assertSame(['1', '1', '2', '1', '1', '0', '1', '0', '0', '0', '0', '0'], $values);

    // The cohort card explains itself in a line under its title.
    $this->assertSession()->elementTextEquals('css', '.stats-card[aria-describedby="stats-cohort-description"] p#stats-cohort-description', 'Projects first published in the period and how far they got');

    // A month counts by week.
    $this->drupalGet('stats', ['query' => ['period' => 'month', 'year' => 2001, 'month' => 11]]);
    $definition = json_decode($this->assertSession()->elementExists('css', '#stats-flow')->getAttribute('data-chart'), TRUE);
    $this->assertSame(['W44', 'W45', 'W46', 'W47', 'W48'], $definition['data']['labels']);
  }

  /**
   * The value of each overview card is the last point of its chart.
   */
  public function testOverviewCharts(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->statusCodeEquals(200);

    // The scenario registers no creatives and three organizations. The site
    // holds no older users or projects.
    $expected = [
      'creatives' => '0',
      'organizations' => '3',
      'open' => '4',
      'ongoing' => '2',
      'completed' => '1',
      'mediated' => '3',
    ];
    foreach ($expected as $key => $value) {
      $id = 'stats-overview-' . $key;
      $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-overview-' . $key . '-title"] .stats-card-value', $value);
      // The creatives have no data in this year and show no chart.
      if ($key === 'creatives') {
        $this->assertSession()->elementNotExists('css', '#' . $id);
        continue;
      }
      $rows = $this->getSession()->getPage()->findAll('xpath', '//div[@id="' . $id . '"]/following-sibling::div//table/tbody/tr');
      $cells = end($rows)->findAll('xpath', 'th|td');
      $this->assertSame($value, trim(end($cells)->getText()), $key);
    }
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
    foreach (['project_list', 'log_list', 'feedback_list', 'user_list'] as $tag) {
      $this->assertContains($tag, $bubbled->getCacheTags());
    }
  }

  /**
   * A period without data shows a dash for the mediation rate.
   */
  public function testEmptyPeriodRendersDashes(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2002]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.stats-card [data-foot="mediation-rate"]', '–');
  }

  /**
   * Bad or removed query arguments fall back to all time.
   */
  public function testBadQueryArgumentsRender200(): void {

    $this->drupalLogin($this->createCreative('creative', 'manager'));
    $organization = $this->createOrganizationUser('organization');
    $queries = [
      ['from' => 'foo'],
      ['from' => '2026-05-01', 'to' => '2026-04-01'],
      ['period' => 'decade'],
      ['compare' => 'x'],
      ['manager' => $organization->id()],
      ['manager' => 'abc'],
    ];
    foreach ($queries as $query) {
      $this->drupalGet('stats', ['query' => $query]);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->elementTextEquals('css', '.stats-period-heading', 'All time');
    }
  }

  /**
   * The activity chart shows the active creatives of the last day per month.
   */
  public function testActivityChart(): void {

    $database = $this->container->get('database');
    $rows = [
      '2001-01-15' => [3, 10],
      '2001-01-31' => [5, 12],
      '2001-02-10' => [2, 9],
    ];
    foreach ($rows as $day => [$active_1d, $active_30d]) {
      $this->touchedDays[$day] = $database->select('stats_activity', 'a')
        ->fields('a')
        ->condition('day', $day)
        ->execute()
        ->fetchAll(FetchAs::Associative);
      $this->container->get('stats.activity_snapshots')->write($day, 'creative', $active_1d, $active_30d);
    }
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->statusCodeEquals(200);
    $table = $this->assertSession()->elementExists('xpath', '//div[@id="stats-activity"]/following-sibling::div//table');
    $headers = array_map(fn($cell) => trim($cell->getText()), $table->findAll('css', 'thead th'));
    $column = array_search('Creatives', $headers, TRUE);
    $this->assertIsInt($column);
    $values = [];
    foreach ($table->findAll('css', 'tbody tr') as $row) {
      $cells = $row->findAll('xpath', 'th|td');
      $values[] = trim($cells[$column]->getText());
    }
    $this->assertSame(['12', '9'], array_slice($values, 0, 2));

    // The tiles show the last snapshot of the period.
    $this->assertSession()->elementTextEquals('css', '.stats-tile[data-tile="active-creatives"] .stats-tile-label', 'Active creatives');
    $this->assertSession()->elementTextEquals('css', '.stats-tile[data-tile="active-creatives"] .stats-tile-value', '9');
    $this->assertSession()->elementExists('css', '.stats-tile[data-tile="active-organizations"]');
  }

  /**
   * A period without snapshots hides the activity chart and its tiles.
   */
  public function testActivityHiddenWithoutSnapshots(): void {

    // Remove the snapshots of 2001, which tearDown() restores.
    $database = $this->container->get('database');
    $rows = $database->select('stats_activity', 'a')
      ->fields('a')
      ->condition('day', ['2001-01-01', '2001-12-31'], 'BETWEEN')
      ->execute()
      ->fetchAll(FetchAs::Associative);
    foreach ($rows as $row) {
      $this->touchedDays[$row['day']][] = $row;
    }
    $database->delete('stats_activity')->condition('day', ['2001-01-01', '2001-12-31'], 'BETWEEN')->execute();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists('css', '#stats-activation');
    $this->assertSession()->elementNotExists('css', '#stats-activity');
    $this->assertSession()->elementNotExists('css', '[data-tile="active-creatives"], [data-tile="active-organizations"]');
    $this->assertSession()->pageTextNotContains('Active users, last 30 days');
  }

  /**
   * The feedback section renders its charts.
   */
  public function testFeedbackSectionRenders(): void {

    $this->setUpScenario();
    $project = Project::load(reset($this->projectIds));
    $this->createFeedback('creative', $project, '2001-03-05', ['field_recommend_boolean' => 1]);
    $this->createFeedback('creative', $project, '2001-03-10', ['field_recommend_boolean' => 0]);
    $this->createFeedback('organization', $project, '2001-03-12', ['field_recommend_boolean' => 1]);
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
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
    // The headings carry no notes on the old graphs, the prospect funnel no
    // repeat organizations.
    $this->assertSession()->pageTextNotContains('old:');
    $this->assertSession()->pageTextNotContains('Repeat organizations');
  }

}
