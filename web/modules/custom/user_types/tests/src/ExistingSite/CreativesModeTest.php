<?php

namespace Drupal\Tests\user_types\ExistingSite;

use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the table and the profiles mode of the creatives finder.
 */
#[Group('user_types')]
class CreativesModeTest extends CreativesTestBase {

  /**
   * The shared name prefix of the created creatives.
   */
  protected string $prefix;

  /**
   * Creates three creatives with a shared name prefix, last seen B, C, A.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->prefix = $this->randomMachineName();
    foreach (['B' => 1000, 'C' => 2000, 'A' => 3000] as $suffix => $access) {
      $this->createCreative()
        ->set('field_name', $this->prefix . ' ' . $suffix)
        ->set('access', $access)
        ->save();
    }
  }

  /**
   * Both modes list the same filtered creatives, latest access first.
   */
  public function testModesShareFiltersAndOrder(): void {

    $this->drupalLogin($this->createManager());
    $query = ['name' => $this->prefix];
    $session = $this->assertSession();

    $this->drupalGet('creatives', ['query' => $query]);
    $session->elementExists('css', 'table');
    $this->assertNamesInOrder(['A', 'C', 'B']);

    $this->drupalGet('creatives', ['query' => $query + ['mode' => 'profiles']]);
    $session->elementNotExists('css', 'table');
    $this->assertNamesInOrder(['A', 'C', 'B']);
  }

  /**
   * The table sorts by its headers but the skills, the profiles by last access.
   */
  public function testTableHeadersSort(): void {

    $this->drupalLogin($this->createManager());
    $session = $this->assertSession();

    $this->drupalGet('creatives', ['query' => ['name' => $this->prefix]]);
    foreach (['Name', 'Mail', 'City', 'Registered', 'Last access'] as $header) {
      $session->elementExists('xpath', '//table//th//a[starts-with(normalize-space(), "' . $header . '")]');
    }
    $session->elementNotExists('xpath', '//table//th//a[starts-with(normalize-space(), "Skills")]');
    $session->elementNotExists('css', 'form.views-exposed-form [name="sort_by"]');
    $session->elementNotExists('css', 'form.views-exposed-form [name="sort_order"]');

    $sorted = ['name' => $this->prefix, 'order' => 'field_name', 'sort' => 'asc'];
    $this->drupalGet('creatives', ['query' => $sorted]);
    $this->assertNamesInOrder(['A', 'B', 'C']);

    $this->drupalGet('creatives', ['query' => $sorted + ['mode' => 'profiles']]);
    $this->assertNamesInOrder(['A', 'C', 'B']);
  }

  /**
   * Managers may view other creatives, but not edit or delete them.
   */
  public function testOperations(): void {

    $this->drupalLogin($this->createManager());
    $session = $this->assertSession();

    $this->drupalGet('creatives', ['query' => ['name' => $this->prefix]]);
    $session->elementExists('xpath', '//table//th[normalize-space() = "Operations"]');
    $views = $this->getSession()->getPage()->findAll('xpath', '//table//td//a[normalize-space() = "View"]');
    $this->assertCount(3, $views);
    $this->assertMatchesRegularExpression('#/user/\d+(\?|$)#', (string) $views[0]->getAttribute('href'));
    $session->elementNotExists('xpath', '//table//td//a[normalize-space() = "Edit"]');
    $session->elementNotExists('xpath', '//table//td//a[normalize-space() = "Delete"]');
  }

  /**
   * Filtering in the profiles mode stays in the profiles mode.
   */
  public function testFilterFormKeepsTheMode(): void {
    $this->drupalLogin($this->createManager());
    $this->drupalGet('creatives', ['query' => ['mode' => 'profiles']]);
    $this->submitForm(['name' => $this->prefix], 'Apply');
    $this->assertStringContainsString('mode=profiles', $this->getSession()->getCurrentUrl());
    $this->assertSession()->elementNotExists('css', 'table');
  }

  /**
   * The toggle links keep the filters and drop the page.
   */
  public function testToggleLinks(): void {

    $this->drupalLogin($this->createManager());
    $this->drupalGet('creatives', ['query' => ['name' => $this->prefix, 'page' => 1]]);
    $page = $this->getSession()->getPage();

    $profiles = $page->findLink('Profiles');
    $this->assertNotNull($profiles);
    $href = (string) $profiles->getAttribute('href');
    $this->assertStringContainsString('name=' . $this->prefix, $href);
    $this->assertStringContainsString('mode=profiles', $href);
    $this->assertStringNotContainsString('page=', $href);
    $this->assertFalse($profiles->hasAttribute('aria-current'));

    $table = $page->findLink('Table');
    $this->assertNotNull($table);
    $href = (string) $table->getAttribute('href');
    $this->assertStringContainsString('name=' . $this->prefix, $href);
    $this->assertStringNotContainsString('mode=', $href);
    $this->assertSame('page', $table->getAttribute('aria-current'));
  }

  /**
   * The table shows 50 creatives per page, the profiles 20.
   */
  public function testPageSizes(): void {
    $this->assertSame(50, $this->itemsPerPage([]));
    $this->assertSame(20, $this->itemsPerPage(['mode' => 'profiles']));
    $this->assertSame(50, $this->itemsPerPage(['mode' => 'other']));
  }

  /**
   * Asserts that the page lists the created creatives in the given order.
   *
   * @param string[] $suffixes
   *   The name suffixes of the creatives, in the expected order.
   */
  protected function assertNamesInOrder(array $suffixes): void {
    $content = $this->getSession()->getPage()->getContent();
    $positions = [];
    foreach ($suffixes as $suffix) {
      $position = strpos($content, $this->prefix . ' ' . $suffix);
      $this->assertNotFalse($position, $suffix);
      $positions[] = $position;
    }
    $sorted = $positions;
    sort($sorted);
    $this->assertSame($sorted, $positions);
  }

  /**
   * Runs the page display with a query and returns its items per page.
   */
  protected function itemsPerPage(array $query): int {
    $current_user = $this->container->get('current_user');
    $previous = $current_user->getAccount();
    $current_user->setAccount($this->createManager());
    $request_stack = $this->container->get('request_stack');
    $request = Request::create('/creatives', 'GET', $query);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request_stack->push($request);
    try {
      $view = Views::getView('creatives');
      $this->assertInstanceOf(ViewExecutable::class, $view);
      $view->executeDisplay('page');
      return (int) $view->getItemsPerPage();
    }
    finally {
      $request_stack->pop();
      $current_user->setAccount($previous);
    }
  }

}
