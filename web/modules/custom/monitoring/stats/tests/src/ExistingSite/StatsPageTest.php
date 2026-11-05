<?php

namespace Drupal\Tests\stats\ExistingSite;

use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\stats\Controller\StatsController;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the stats page with the period bar and the KPI tiles.
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
   * Gets the text of a KPI tile value.
   */
  protected function kpi(string $key): string {
    $element = $this->assertSession()->elementExists('css', '.stats-tile[data-tile="' . $key . '"] .stats-tile-value');
    return trim($element->getText());
  }

  /**
   * The tiles show the cohort numbers and the bar links between periods.
   */
  public function testKpisForScenario(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '2001');
    $this->assertSame('6', $this->kpi('published'));
    $this->assertSame('3', $this->kpi('mediated'));
    $this->assertSame('75 %', $this->kpi('mediation-rate'));
    $this->assertSame('9.0 d', $this->kpi('median'));
    $this->assertSession()->pageTextNotContains('vs ');

    // The period bar holds the presets, the neighbor years and the range form,
    // without the removed comparison and manager filter.
    $this->assertSession()->fieldNotExists('compare');
    $this->assertSession()->fieldNotExists('manager');
    $this->assertSession()->linkExists('All time');
    $this->assertSession()->linkExists('‹ 2000');
    $this->assertSession()->linkExists('2002 ›');
    $this->clickLink('2002 ›');
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '2002');
    $this->assertSame('0', $this->kpi('published'));
  }

  /**
   * The range form selects a custom period, or the preset it covers.
   */
  public function testRangeForm(): void {

    $this->drupalLogin($this->createCreative('creative', 'manager'));

    // Gin styles the date fields and the primary button of the native form.
    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $from = $this->assertSession()->elementExists('css', '.stats-range-form input[name="from"].form-element--type-date');
    $this->assertSame('2001-01-01', $from->getValue());
    $to = $this->assertSession()->elementExists('css', '.stats-range-form input[name="to"].form-element--type-date');
    $this->assertSame('2001-12-31', $to->getValue());
    $this->assertSession()->elementExists('css', '.stats-range-form input.button--primary[value="Apply"]');

    $this->submitForm(['from' => '2001-03-05', 'to' => '2001-04-20'], 'Apply');
    $query = [];
    parse_str((string) parse_url($this->getUrl(), PHP_URL_QUERY), $query);
    $this->assertSame(['period' => 'custom', 'from' => '2001-03-05', 'to' => '2001-04-20'], $query);
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '05.03.2001 – 20.04.2001');
    $this->assertSession()->linkExists('‹ 17.01.2001 – 04.03.2001');
    $this->assertSame('2001-03-05', $this->assertSession()->fieldExists('from')->getValue());

    // A range covering a calendar quarter is that quarter.
    $this->submitForm(['from' => '2001-04-01', 'to' => '2001-06-30'], 'Apply');
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', 'Q2 2001');
    $this->assertSession()->linkExists('‹ Q1 2001');

    // A range ending before it starts stays on the form with an error.
    $this->submitForm(['from' => '2001-05-01', 'to' => '2001-04-01'], 'Apply');
    $this->assertSession()->pageTextContains('The end date must not be before the start date.');
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', 'Q2 2001');
  }

  /**
   * The projects section renders its charts with local Chart.js and tables.
   */
  public function testProjectChartsRender(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->statusCodeEquals(200);
    foreach (['stats-flow', 'stats-funnel', 'stats-stages'] as $id) {
      $this->assertSession()->elementExists('css', '#' . $id);
    }
    // The card labels the chart, which draws no title of its own but keeps it
    // for the figure label and the caption of its table.
    $this->assertSession()->elementTextEquals('css', '.stats-card[aria-labelledby="stats-flow-title"] > h3#stats-flow-title', 'Project flow');
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
    foreach (['project_list', 'log_list', 'feedback_list', 'user_list', 'course_progress_list'] as $tag) {
      $this->assertContains($tag, $bubbled->getCacheTags());
    }
  }

  /**
   * A period without data shows dashes for rate and median, zeros for counts.
   */
  public function testEmptyPeriodRendersDashes(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2002]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('0', $this->kpi('published'));
    $this->assertSame('–', $this->kpi('mediation-rate'));
    $this->assertSame('–', $this->kpi('median'));
  }

  /**
   * Bad or removed query arguments fall back to the default period.
   */
  public function testBadQueryArgumentsRender200(): void {

    $this->drupalLogin($this->createCreative('creative', 'manager'));
    $organization = $this->createOrganizationUser('organization');
    $year = (new \DateTimeImmutable('now', $this->timezone()))->format('Y');

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
      $this->assertSession()->elementTextContains('css', '.stats-period-heading', $year);
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
  }

  /**
   * The feedback and academy section renders its charts and tiles.
   */
  public function testFeedbackSectionRenders(): void {

    $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Feedback and academy');
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
    $this->assertSession()->elementExists('css', '.stats-tile[data-tile="academy-enrolled"] .stats-tile-value');
    $this->assertSession()->elementExists('css', '.stats-tile[data-tile="academy-completed"] .stats-tile-value');
  }

}
