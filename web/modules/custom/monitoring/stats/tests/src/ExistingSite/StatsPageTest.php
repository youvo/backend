<?php

namespace Drupal\Tests\stats\ExistingSite;

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

}
