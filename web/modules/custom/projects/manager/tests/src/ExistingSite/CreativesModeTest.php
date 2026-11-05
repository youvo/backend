<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the table and the profiles mode of the creatives finder.
 */
#[Group('manager')]
class CreativesModeTest extends ManagerExistingSiteTestBase {

  /**
   * The shared name prefix of the created creatives.
   */
  protected string $prefix;

  /**
   * Creates three creatives with a shared name prefix.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->prefix = $this->randomMachineName();
    foreach (['B', 'C', 'A'] as $suffix) {
      $this->createCreative()->set('field_name', $this->prefix . ' ' . $suffix)->save();
    }
  }

  /**
   * Both modes list the same filtered creatives in the same order.
   */
  public function testModesShareFiltersAndOrder(): void {

    $this->drupalLogin($this->createManager());
    $query = [
      'name' => $this->prefix,
      'sort_by' => 'field_name_value',
      'sort_order' => 'ASC',
    ];
    $session = $this->assertSession();

    $this->drupalGet('creatives', ['query' => $query]);
    $session->elementExists('css', 'table');
    $this->assertNamesInOrder();

    $this->drupalGet('creatives', ['query' => $query + ['mode' => 'profiles']]);
    $session->elementNotExists('css', 'table');
    $this->assertNamesInOrder();
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
   * Asserts that the page lists the created creatives by name.
   */
  protected function assertNamesInOrder(): void {
    $content = $this->getSession()->getPage()->getContent();
    $positions = [];
    foreach (['A', 'B', 'C'] as $suffix) {
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
