<?php

namespace Drupal\Tests\stats\ExistingSite;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the totals of the stats calculator as of a moment.
 */
#[Group('stats')]
class StatsCalculatorTest extends StatsTestBase {

  /**
   * Gets the timestamp of 00:00 local on a Y-m-d date.
   */
  protected function midnight(string $date): int {
    return (new \DateTimeImmutable($date . ' 00:00:00', $this->timezone()))->getTimestamp();
  }

  /**
   * The project totals follow the lifecycle history up to the timestamp.
   *
   * The site holds no lifecycle history before 2025, so the counts in 2001 are
   * those of the scenario projects. Archived projects count, too.
   */
  public function testProjectTotalsAsOf(): void {

    $this->setUpScenario();
    $calculator = $this->container->get('stats.calculator');

    // On 2001-03-15, A is completed, B and F are ongoing, C and D are open.
    // E has no history yet and G and H are not created.
    $until = $this->midnight('2001-03-15');
    $this->assertSame(2, $calculator->countOpenProjects($until));
    $this->assertSame(2, $calculator->countOngoingProjects($until));
    $this->assertSame(1, $calculator->countCompletedProjects($until));
    $this->assertSame(3, $calculator->countMediatedProjects($until));

    // At the end of 2001, E was reset and is open again, as are C, D and the
    // archived H. G was declined from draft and never counts.
    $until = $this->midnight('2002-01-01');
    $this->assertSame(4, $calculator->countOpenProjects($until));
    $this->assertSame(2, $calculator->countOngoingProjects($until));
    $this->assertSame(1, $calculator->countCompletedProjects($until));
    $this->assertSame(3, $calculator->countMediatedProjects($until));

    // Before the first history item, nothing counts.
    $this->assertSame(0, $calculator->countOpenProjects($this->midnight('2000-12-01')));

    // Completing A at noon counts from the following second on.
    $noon = $this->noon('2001-03-01');
    $this->assertSame(0, $calculator->countCompletedProjects($noon));
    $this->assertSame(1, $calculator->countCompletedProjects($noon + 1));
  }

  /**
   * The user totals count the users created before the timestamp.
   */
  public function testUserTotalsAsOf(): void {

    $this->setUpScenario();
    $calculator = $this->container->get('stats.calculator');

    // The organizations of the scenario were created on 2000-06-01,
    // 2001-01-01 and 2001-03-01, the site holds none from before 2025.
    $this->assertSame(0, $calculator->countOrganizations($this->midnight('2000-01-01')));
    $this->assertSame(2, $calculator->countOrganizations($this->midnight('2001-02-01')));
    $this->assertSame(3, $calculator->countOrganizations($this->midnight('2001-03-15')));

    // The creatives of the scenario were created now.
    $this->assertSame(0, $calculator->countCreatives($this->midnight('2001-03-15')));
    $future = time() + 86400;
    $this->assertSame($calculator->countCreatives(), $calculator->countCreatives($future));
    $this->assertSame($calculator->countOrganizations(), $calculator->countOrganizations($future));
    $this->assertSame($calculator->countMediatedProjects(), $calculator->countMediatedProjects($future));
  }

}
