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
    $element = $this->assertSession()->elementExists('css', '.stats-kpi--' . $key . ' .stats-kpi__value');
    return trim($element->getText());
  }

  /**
   * Gets the text of a KPI tile change.
   */
  protected function delta(string $key): ?string {
    $element = $this->getSession()->getPage()->find('css', '.stats-kpi--' . $key . ' .stats-kpi__delta');
    return $element ? trim($element->getText()) : NULL;
  }

  /**
   * The tiles show the cohort numbers, the changes and the cache metadata.
   */
  public function testKpisForScenario(): void {

    $manager = $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $query = ['period' => 'year', 'year' => 2001, 'manager' => $manager->id()];
    $this->drupalGet('stats', ['query' => $query]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', '.stats-period-heading', '2001');
    $this->assertSame('6', $this->kpi('published'));
    $this->assertSame('3', $this->kpi('mediated'));
    $this->assertSame('75 %', $this->kpi('mediation-rate'));
    $this->assertSame('9.0 d', $this->kpi('median'));

    // The year 2000 holds project F only, published and mediated (in 2001).
    $this->assertSame('+5', $this->delta('published'));
    $this->assertSame('+2', $this->delta('mediated'));
    $this->assertSame('−25 pp', $this->delta('mediation-rate'));
    $this->assertNull($this->delta('median'));

    // Against the same period one year earlier, 2002 has no data at all.
    $this->drupalGet('stats', [
      'query' => [
        'period' => 'year',
        'year' => 2002,
        'compare' => 'last_year',
        'manager' => $manager->id(),
      ],
    ]);
    $this->assertSame('0', $this->kpi('published'));
    $this->assertSame('−6', $this->delta('published'));
    $this->assertSame('−3', $this->delta('mediated'));

    // The period bar carries the form, the presets and the navigation.
    $this->assertSession()->elementExists('css', 'form.stats-period-form select[name="manager"] option[value="' . $manager->id() . '"]');
    $this->assertSession()->elementExists('css', 'form.stats-period-form select[name="compare"]');
    $this->assertSession()->linkExists('All time');
  }

  /**
   * The projects section renders its charts with local Chart.js and tables.
   */
  public function testProjectChartsRender(): void {

    $manager = $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $query = ['period' => 'year', 'year' => 2001, 'manager' => $manager->id()];
    $this->drupalGet('stats', ['query' => $query]);
    $this->assertSession()->statusCodeEquals(200);
    foreach (['stats-flow', 'stats-funnel', 'stats-stages'] as $id) {
      $this->assertSession()->elementExists('css', '#' . $id);
    }
    $html = $this->getSession()->getPage()->getContent();
    $this->assertStringContainsString('/libraries/chart.js/dist/chart.umd.js', $html);
    $this->assertStringNotContainsString('unpkg.com', $html);

    // The table has one row per month and one column per series.
    $table = $this->assertSession()->elementExists('xpath', '//div[@id="stats-flow"]/following-sibling::div//table');
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

    foreach (['url.query_args', 'user.permissions'] as $context) {
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

    $manager = $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $query = ['period' => 'year', 'year' => 2002, 'manager' => $manager->id()];
    $this->drupalGet('stats', ['query' => $query]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSame('0', $this->kpi('published'));
    $this->assertSame('–', $this->kpi('mediation-rate'));
    $this->assertSame('–', $this->kpi('median'));
    $this->assertNull($this->delta('mediation-rate'));
  }

  /**
   * Bad query arguments fall back to the default period with a 200.
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

    $manager = $this->setUpScenario();
    $this->drupalLogin($this->createCreative('creative', 'manager'));

    $this->drupalGet('stats', ['query' => ['period' => 'year', 'year' => 2001, 'manager' => $manager->id()]]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Feedback and academy');
    foreach (['stats-feedback-creative', 'stats-feedback-organization'] as $id) {
      $this->assertSession()->elementExists('css', '#' . $id);
    }
    $this->assertSession()->elementExists('css', '.stats-kpi--academy-enrolled .stats-kpi__value');
    $this->assertSession()->elementExists('css', '.stats-kpi--academy-completed .stats-kpi__value');
  }

}
