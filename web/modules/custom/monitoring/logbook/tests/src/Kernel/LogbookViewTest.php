<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Entity\View;
use Drupal\views\Views;

/**
 * Tests the logbook view.
 *
 * @group logbook
 */
class LogbookViewTest extends LogbookKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Rendering the view needs a theme and the date formats.
    $this->installConfig(['system']);
    // The event text is computed from the log text entity of the pattern.
    $this->installEntitySchema('log_text');
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $path = \Drupal::service('extension.list.module')->getPath('logbook') . '/config/install/views.view.logbook.yml';
    View::create(Yaml::decode(file_get_contents($path)))->save();
    $this->createPattern('project_apply', ['observable' => TRUE, 'tone' => 'positive']);
    $this->createPattern('project_publish', ['observable' => TRUE, 'public' => TRUE, 'promote' => TRUE]);
    $this->createPattern('project_complete', [
      'observable' => TRUE,
      'public' => TRUE,
      'promote' => TRUE,
      'tone' => 'celebrate',
    ]);
    $this->createPattern('project_notify', ['observable' => FALSE]);
    $this->createPattern('organization_create', ['observable' => TRUE, 'hidden' => TRUE]);
    $this->createPattern('feedback_off', ['observable' => TRUE, 'status' => FALSE]);
  }

  /**
   * Executes the page and returns the ids of the result, newest first.
   *
   * @return int[]
   *   The log ids.
   */
  protected function ids(array $input = []): array {
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $view->setExposedInput($input);
    $view->execute();
    return array_map(static fn ($row) => (int) $row->_entity->id(), $view->result);
  }

  /**
   * Renders the page to HTML.
   */
  protected function renderPage(array $input = []): string {
    // The view checks access: render as a user who may see it.
    $this->setCurrentUser($this->createUser(['administer log pattern']));
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $view->setExposedInput($input);
    $render = $view->buildRenderable('page_1');
    return (string) $this->container->get('renderer')->renderInIsolation($render);
  }

  /**
   * Only enabled observable patterns are listed, newest first.
   */
  public function testScope(): void {
    $a = $this->createLog('project_apply', ['created' => 100]);
    $b = $this->createLog('project_publish', ['created' => 200]);
    $this->createLog('project_notify', ['created' => 300]);
    $this->createLog('feedback_off', ['created' => 400]);
    $this->createLog('organization_create', ['created' => 500]);
    $this->assertSame([(int) $b->id(), (int) $a->id()], $this->ids());
  }

  /**
   * An absent hidden input still excludes hidden patterns.
   *
   * An unchecked box sends nothing.
   */
  public function testHiddenInput(): void {
    $a = $this->createLog('project_apply', ['created' => 100]);
    $h = $this->createLog('organization_create', ['created' => 200]);
    $this->assertSame([(int) $a->id()], $this->ids([]));
    $this->assertSame([(int) $h->id(), (int) $a->id()], $this->ids(['hidden' => 1]));
  }

  /**
   * The group and visibility filters, also when nothing matches.
   */
  public function testGroupAndVisibility(): void {
    $apply = $this->createLog('project_apply', ['created' => 100]);
    $publish = $this->createLog('project_publish', ['created' => 200]);
    $this->assertSame([(int) $publish->id()], $this->ids(['group' => 'lifecycle']));
    $this->assertSame([(int) $apply->id()], $this->ids(['group' => 'creatives']));
    $this->assertSame([], $this->ids(['group' => 'organizations']));
    $this->assertSame([(int) $publish->id()], $this->ids(['visibility' => 'public']));
    $this->assertSame([(int) $apply->id()], $this->ids(['visibility' => 'internal']));
    $this->assertSame([], $this->ids(['group' => 'creatives', 'visibility' => 'public']));
  }

  /**
   * An unknown filter value does not throw.
   */
  public function testUnknownFilterValue(): void {
    $this->createLog('project_apply', ['created' => 100]);
    $this->assertIsArray($this->ids(['group' => 'bogus']));
    $this->assertIsArray($this->ids(['visibility' => 'bogus']));
  }

  /**
   * The search matches message and project title, and % and _ are literal.
   */
  public function testSearch(): void {
    $project = $this->createLogbookProject('Zebra Garden');
    $with_project = $this->createLog('project_apply', ['project' => $project->id(), 'created' => 100]);
    $percent = $this->createLog('project_apply', ['message' => 'Rabatt 50% heute', 'created' => 200]);
    $other = $this->createLog('project_apply', ['message' => 'Rabatt 500 heute', 'created' => 300]);
    $this->assertSame([(int) $with_project->id()], $this->ids(['search' => 'Zebra']));
    $this->assertSame([(int) $percent->id()], $this->ids(['search' => '50%']));
    $this->assertSame([], $this->ids(['search' => 'Rabatt_50']));
    $this->assertContains((int) $other->id(), $this->ids(['search' => 'Rabatt']));
  }

  /**
   * The created filter limits the time range.
   */
  public function testCreatedRange(): void {
    $this->createLog('project_apply', ['created' => strtotime('2026-06-01 12:00')]);
    $in = $this->createLog('project_apply', ['created' => strtotime('2026-06-10 12:00')]);
    $this->createLog('project_apply', ['created' => strtotime('2026-06-20 12:00')]);
    $this->assertSame([(int) $in->id()], $this->ids(['created' => ['min' => '2026-06-05', 'max' => '2026-06-15']]));
  }

  /**
   * More than 50 logs give a second page.
   */
  public function testPager(): void {
    for ($i = 1; $i <= 51; $i++) {
      $this->createLog('project_apply', ['created' => $i]);
    }
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $view->execute();
    $this->assertCount(50, $view->result);
    $this->assertSame(51, (int) $view->pager->getTotalItems());
  }

  /**
   * A log without project or organization is listed and renders.
   */
  public function testLogWithoutProject(): void {
    $this->createLog('project_apply', ['message' => 'No project here', 'created' => 100]);
    $html = $this->renderPage();
    $this->assertStringContainsString('No project here', $html);
  }

  /**
   * Cells: tone pill, message, visibility icons.
   */
  public function testCells(): void {
    $this->createLog('project_apply', ['message' => 'Hello <b>world</b>', 'created' => 100]);
    $this->createLog('project_publish', ['created' => 200]);
    $html = $this->renderPage();
    $this->assertStringContainsString('logbook-type--positive', $html);
    $this->assertStringContainsString('logbook-event__message', $html);
    $this->assertStringContainsString('Hello &lt;b&gt;world&lt;/b&gt;', $html);
    $this->assertStringContainsString('logbook-icon--lock', $html);
    $this->assertStringContainsString('aria-label="Internal: only managers of the organization"', $html);
    $this->assertStringContainsString('logbook-icon--globe', $html);
    $this->assertStringContainsString('logbook-icon--star', $html);
  }

  /**
   * The page needs the administer permission.
   */
  public function testAccess(): void {
    $access_manager = $this->container->get('access_manager');
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses permission checks: use it up.
    $this->createUser();
    $user = $this->createUser([]);
    $this->assertFalse($access_manager->checkNamedRoute('view.logbook.page_1', [], $user));
    $admin = $this->createUser(['administer log pattern']);
    $this->assertTrue($access_manager->checkNamedRoute('view.logbook.page_1', [], $admin));
  }

  /**
   * Only a celebrate pattern colors the row and the library is attached.
   */
  public function testCelebrateRow(): void {
    $this->setCurrentUser($this->createUser(['administer log pattern']));
    $this->createLog('project_apply', ['created' => 100]);
    $this->assertStringNotContainsString('logbook-row--celebrate', $this->renderPage());
    $this->createLog('project_complete', ['created' => 200]);
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $render = $view->buildRenderable('page_1');
    $html = (string) $this->container->get('renderer')->renderInIsolation($render);
    $this->assertSame(1, substr_count($html, 'logbook-row--celebrate'));
    $this->assertContains('logbook/table', $render['#attached']['library'] ?? []);
  }

}
