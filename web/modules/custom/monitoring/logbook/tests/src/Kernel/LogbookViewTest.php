<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\logbook\Entity\LogPattern;
use Drupal\organizations\Entity\Organization;
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
    $this->setCurrentUser($this->createUser(['view observable log']));
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $view->setExposedInput($input);
    $render = $view->buildRenderable('page_1');
    return (string) $this->container->get('renderer')->renderInIsolation($render);
  }

  /**
   * The flags of the patterns decide what is listed, newest first.
   */
  public function testScopeFollowsThePatternFlags(): void {
    $apply = $this->createLog('project_apply', ['created' => 100]);
    $publish = $this->createLog('project_publish', ['created' => 200]);
    $notify = $this->createLog('project_notify', ['created' => 300]);
    $this->createLog('feedback_off', ['created' => 400]);
    // Not observable and disabled patterns are not listed.
    $this->assertSame([(int) $publish->id(), (int) $apply->id()], $this->ids());
    // Making a pattern observable lists it, without any change of the view.
    $pattern = LogPattern::load('project_notify');
    $pattern->set('observable', TRUE)->save();
    $this->assertSame([(int) $notify->id(), (int) $publish->id(), (int) $apply->id()], $this->ids());
    // A pattern that is created later is listed as well.
    $this->createPattern('project_new', ['observable' => TRUE]);
    $new = $this->createLog('project_new', ['created' => 500]);
    $this->assertContains((int) $new->id(), $this->ids());
    // Disabling a pattern removes its logs from the list.
    $pattern->set('status', FALSE)->save();
    $this->assertNotContains((int) $notify->id(), $this->ids());
  }

  /**
   * Deleting a log pattern does not take the view with it.
   *
   * The delete form of a config entity deletes the config that depends on it.
   */
  public function testDeletingPatternKeepsTheView(): void {
    // The patterns exist now: saving the view records what it depends on.
    View::load('logbook')->save();
    $changes = $this->container->get('config.manager')
      ->getConfigEntitiesToChangeOnDependencyRemoval('config', ['logbook.log_pattern.project_apply']);
    $deleted = array_map(static fn ($entity) => $entity->getConfigDependencyName(), $changes['delete']);
    $this->assertNotContains('views.view.logbook', $deleted);
  }

  /**
   * The exposed pattern filter limits the list to one pattern.
   */
  public function testTypeFilter(): void {
    $apply = $this->createLog('project_apply', ['created' => 100]);
    $publish = $this->createLog('project_publish', ['created' => 200]);
    $this->assertSame([(int) $apply->id()], $this->ids(['type' => 'project_apply']));
    $this->assertSame([(int) $publish->id()], $this->ids(['type' => 'project_publish']));
    $this->assertSame([(int) $publish->id(), (int) $apply->id()], $this->ids(['type' => 'All']));
  }

  /**
   * A pattern outside the list cannot be reached through the filter.
   */
  public function testUnknownOrUnlistedType(): void {
    $this->createLog('project_apply', ['created' => 100]);
    $notify = $this->createLog('project_notify', ['created' => 200]);
    $this->assertNotContains((int) $notify->id(), $this->ids(['type' => 'project_notify']));
    $this->assertNotContains((int) $notify->id(), $this->ids(['type' => 'bogus']));
  }

  /**
   * A text tag matches the message, the project title and the organization.
   *
   * The wildcards % and _ match literally.
   */
  public function testTextMatchesMessageProjectTitleAndOrganizationName(): void {
    $project = $this->createLogbookProject('Zebra Garden');
    $with_project = $this->createLog('project_apply', ['project' => $project->id(), 'created' => 100]);
    $percent = $this->createLog('project_apply', ['message' => 'Rabatt 50% heute', 'created' => 200]);
    $other = $this->createLog('project_apply', ['message' => 'Rabatt 500 heute', 'created' => 300]);
    $organization = $this->createNamedOrganization('Kranich e.V.');
    $with_organization = $this->createLog('project_apply', ['organization' => $organization->id(), 'created' => 400]);
    $this->assertSame([(int) $with_project->id()], $this->ids(['tags' => ['text:Zebra']]));
    $this->assertSame([(int) $percent->id()], $this->ids(['tags' => ['text:50%']]));
    $this->assertSame([], $this->ids(['tags' => ['text:Rabatt_50']]));
    $this->assertContains((int) $other->id(), $this->ids(['tags' => ['text:Rabatt']]));
    $this->assertSame([(int) $with_organization->id()], $this->ids(['tags' => ['text:kranich']]));
  }

  /**
   * A person or organization tag matches every role in a log.
   */
  public function testTagMatchesEveryRole(): void {
    $creative = $this->createCreative();
    $organization = $this->createNamedOrganization('Kranich e.V.');
    $author = $this->createLog('project_apply', ['author' => $creative->id(), 'created' => 100]);
    $member = $this->createLog('project_apply', ['creatives' => [$creative->id()], 'created' => 200]);
    $managed = $this->createLog('project_apply', ['manager' => $creative->id(), 'created' => 300]);
    $owned = $this->createLog('project_apply', ['organization' => $organization->id(), 'created' => 400]);
    $this->createLog('project_apply', ['created' => 500]);
    $this->assertSame(
      [(int) $managed->id(), (int) $member->id(), (int) $author->id()],
      $this->ids(['tags' => ['user:' . $creative->id()]]),
    );
    $this->assertSame([(int) $owned->id()], $this->ids(['tags' => ['user:' . $organization->id()]]));
  }

  /**
   * An organization tag finds the logs of its projects without organization.
   */
  public function testOrganizationTagFindsFeedbackLogsOfItsProjects(): void {
    $this->createPattern('feedback_complete', ['observable' => TRUE]);
    $project = $this->createLogbookProject('Kranichflug');
    $feedback = $this->createLog('feedback_complete', ['project' => $project->id(), 'created' => 100]);
    $this->createLog('feedback_complete', ['project' => $this->createLogbookProject()->id(), 'created' => 200]);
    $this->assertSame([(int) $feedback->id()], $this->ids(['tags' => ['user:' . $project->getOwnerId()]]));
  }

  /**
   * Tags of people, organizations and projects add up.
   */
  public function testTagsCombineWithOr(): void {
    $creative = $this->createCreative();
    $project = $this->createLogbookProject('Kranichflug');
    $by_creative = $this->createLog('project_apply', ['creatives' => [$creative->id()], 'created' => 100]);
    $of_project = $this->createLog('project_apply', ['project' => $project->id(), 'created' => 200]);
    $this->createLog('project_apply', ['created' => 300]);
    $this->assertSame(
      [(int) $of_project->id(), (int) $by_creative->id()],
      $this->ids(['tags' => ['user:' . $creative->id(), 'project:' . $project->id()]]),
    );
  }

  /**
   * Text tags narrow the result, each of them.
   */
  public function testTextNarrowsTags(): void {
    $project = $this->createLogbookProject('Kranichflug');
    $feedback = $this->createLog('project_apply', [
      'project' => $project->id(),
      'message' => 'Feedback kam',
      'created' => 100,
    ]);
    $this->createLog('project_apply', ['project' => $project->id(), 'message' => 'Nichts', 'created' => 200]);
    $this->createLog('project_apply', ['message' => 'Feedback woanders', 'created' => 300]);
    $this->assertSame([(int) $feedback->id()], $this->ids(['tags' => ['project:' . $project->id(), 'text:Feedback']]));
    $both = $this->createLog('project_apply', ['message' => 'Zebra im Garden', 'created' => 400]);
    $this->createLog('project_apply', ['message' => 'Zebra allein', 'created' => 500]);
    $this->assertSame([(int) $both->id()], $this->ids(['tags' => ['text:Zebra', 'text:Garden']]));
  }

  /**
   * A log with two tagged creatives is listed once.
   */
  public function testLogWithTwoTaggedCreativesIsListedOnce(): void {
    $first = $this->createCreative();
    $second = $this->createCreative();
    $log = $this->createLog('project_apply', ['creatives' => [$first->id(), $second->id()], 'created' => 100]);
    $this->assertSame(
      [(int) $log->id()],
      $this->ids(['tags' => ['user:' . $first->id(), 'user:' . $second->id()]]),
    );
  }

  /**
   * Invalid tags and ids of deleted entities are ignored, as in the bar.
   */
  public function testInvalidTagsAreIgnored(): void {
    $this->createLog('project_apply', ['created' => 100]);
    $with_message = $this->createLog('project_apply', ['message' => 'Kranich hallo', 'created' => 200]);
    $all = $this->ids();
    $this->assertSame($all, $this->ids(['tags' => ['node:1', 'user:abc', 'text:  ']]));
    $this->assertSame($all, $this->ids(['tags' => 'garbage']));
    $this->assertSame($all, $this->ids(['tags' => ['user:999999']]));
    $this->assertSame([(int) $with_message->id()], $this->ids(['tags' => ['user:999999', 'text:Kranich']]));
  }

  /**
   * Invalid input does not show up in the search bar.
   */
  public function testInvalidTagsAreNotShownInTheBar(): void {
    $html = $this->renderPage(['tags' => ['node:1', '<b>x</b>']]);
    $this->assertStringNotContainsString('node:1', $html);
    $this->assertStringNotContainsString('&lt;b&gt;x', $html);
  }

  /**
   * Typing an organization name finds the logs of its projects, as its tag.
   */
  public function testOrganizationNameFindsFeedbackLogsOfItsProjects(): void {
    $this->createPattern('feedback_complete', ['observable' => TRUE]);
    $organization = $this->createNamedOrganization('Kranich e.V.');
    $project = $this->createLogbookProject('Flug');
    $project->setOwnerId($organization->id())->save();
    $feedback = $this->createLog('feedback_complete', ['project' => $project->id(), 'created' => 100]);
    $this->createLog('feedback_complete', ['project' => $this->createLogbookProject('Andere')->id(), 'created' => 200]);
    $this->assertSame([(int) $feedback->id()], $this->ids(['tags' => ['text:Kranich']]));
  }

  /**
   * Cached results follow renamed projects and organizations.
   */
  public function testResultsFollowRenamedProjectsAndOrganizations(): void {
    $project = $this->createLogbookProject('Zebra Garden');
    $organization = $this->createNamedOrganization('Kranich e.V.');
    $log = $this->createLog('project_apply', [
      'project' => $project->id(),
      'organization' => $organization->id(),
      'created' => 100,
    ]);
    $this->assertSame([(int) $log->id()], $this->ids(['tags' => ['text:Zebra']]));
    $project->setTitle('Anderer Titel')->save();
    $this->assertSame([], $this->ids(['tags' => ['text:Zebra']]));
    $this->assertSame([(int) $log->id()], $this->ids(['tags' => ['text:Kranich']]));
    $organization->set('field_name', 'Storch e.V.')->save();
    $this->assertSame([], $this->ids(['tags' => ['text:Kranich']]));
  }

  /**
   * The search bar replaces the text box.
   */
  public function testRenderedFormHasSearchBar(): void {
    $html = $this->renderPage();
    $this->assertStringContainsString('logbook-search', $html);
    $this->assertStringContainsString('/logbook/search/autocomplete', $html);
    $this->assertStringNotContainsString('Suchen …', $html);
  }

  /**
   * Creates an organization with a name.
   */
  protected function createNamedOrganization(string $name): Organization {
    $organization = Organization::create(['name' => $this->randomMachineName(), 'field_name' => $name]);
    $organization->save();
    return $organization;
  }

  /**
   * The created filter limits the time range, the end day included.
   */
  public function testCreatedRange(): void {
    $this->createLog('project_apply', ['created' => strtotime('2026-06-01 12:00')]);
    $start = $this->createLog('project_apply', ['created' => strtotime('2026-06-05 00:30')]);
    $in = $this->createLog('project_apply', ['created' => strtotime('2026-06-10 12:00')]);
    $end = $this->createLog('project_apply', ['created' => strtotime('2026-06-15 23:30')]);
    $this->createLog('project_apply', ['created' => strtotime('2026-06-16 00:30')]);
    $this->createLog('project_apply', ['created' => strtotime('2026-06-20 12:00')]);
    $range = ['created' => ['min' => '2026-06-05', 'max' => '2026-06-15']];
    $this->assertSame([(int) $end->id(), (int) $in->id(), (int) $start->id()], $this->ids($range));
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
    // The visibility column has an accessible name, but no visible title.
    $this->assertStringContainsString('Sichtbarkeit</th>', $html);
    $this->assertStringContainsString('logbook-icon--lock', $html);
    $this->assertStringContainsString('aria-label="Internal: only managers of the organization"', $html);
    $this->assertStringContainsString('logbook-icon--star', $html);
  }

  /**
   * The page needs the permission to view observable logs.
   */
  public function testAccess(): void {
    $access_manager = $this->container->get('access_manager');
    $this->container->get('router.builder')->rebuild();
    // User 1 bypasses permission checks: use it up.
    $this->createUser();
    $nobody = $this->createUser([]);
    $this->assertFalse($access_manager->checkNamedRoute('view.logbook.page_1', [], $nobody));
    // Administering the log patterns is not the permission of the logbook.
    $pattern_admin = $this->createUser(['administer log pattern']);
    $this->assertFalse($access_manager->checkNamedRoute('view.logbook.page_1', [], $pattern_admin));
    $observer = $this->createUser(['view observable log']);
    $this->assertTrue($access_manager->checkNamedRoute('view.logbook.page_1', [], $observer));
  }

  /**
   * Only a celebrate pattern colors the row and the library is attached.
   */
  public function testCelebrateRow(): void {
    $this->setCurrentUser($this->createUser(['view observable log']));
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

  /**
   * The date range uses date inputs and the pattern select says what all means.
   */
  public function testExposedFormWidgets(): void {
    $html = $this->renderPage(['created' => ['min' => '2026-06-05', 'max' => '2026-06-15']]);
    $this->assertMatchesRegularExpression('/<input[^>]*type="date"[^>]*name="created\[min\]"/', $html);
    $this->assertMatchesRegularExpression('/<input[^>]*type="date"[^>]*name="created\[max\]"/', $html);
    $this->assertStringContainsString('value="2026-06-05"', $html);
    // The end day is made inclusive in the form values, not in the field.
    $this->assertStringContainsString('value="2026-06-15"', $html);
    $this->assertStringNotContainsString('23:59:59', $html);
    $this->assertStringContainsString('All types', $html);
    $this->assertStringNotContainsString('- Any -', $html);
  }

  /**
   * Editing a log pattern invalidates the rendered list.
   */
  public function testListDependsOnLogPatterns(): void {
    $this->setCurrentUser($this->createUser(['view observable log']));
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $render = $view->buildRenderable('page_1');
    $this->container->get('renderer')->renderInIsolation($render);
    $this->assertContains('config:log_pattern_list', $render['#cache']['tags'] ?? []);
  }

  /**
   * The search bar comes first, then the pattern and the date range.
   */
  public function testExposedFilterOrder(): void {
    $html = $this->renderPage();
    preg_match_all('/<(?:input|select)[^>]*name="([^"]+)"/', substr($html, 0, strpos($html, '<table') ?: NULL), $matches);
    $names = array_values(array_diff($matches[1], ['form_build_id', 'form_id']));
    $this->assertSame(['tags', 'type', 'created[min]', 'created[max]'], $names);
  }

  /**
   * Internal logs show a lock, public logs a globe, promoted logs a star only.
   */
  public function testVisibilityIcons(): void {
    $this->createPattern('project_mediate', ['observable' => TRUE, 'public' => TRUE]);
    $this->createLog('project_apply', ['created' => 100]);
    $this->createLog('project_mediate', ['created' => 200]);
    $this->createLog('project_publish', ['created' => 300]);
    $html = $this->renderPage();
    preg_match_all('/<tr>.*?<\/tr>/s', $html, $rows);
    $icons = [];
    foreach ($rows[0] as $row) {
      if (preg_match('/logbook-type[^>]*>([^<]+)</', $row, $type)) {
        preg_match_all('/logbook-icon--(\w+)/', $row, $found);
        $icons[$type[1]] = $found[1];
      }
    }
    $this->assertSame(['lock'], $icons['Apply']);
    $this->assertSame(['globe'], $icons['Mediate']);
    $this->assertSame(['star'], $icons['Publish']);
  }

  /**
   * Gets the patterns that the pattern select of the page offers.
   *
   * @return list<string>
   *   The pattern ids, without the option for all.
   */
  protected function typeOptions(): array {
    preg_match('/<select[^>]*name="type".*?<\/select>/s', $this->renderPage(), $select);
    preg_match_all('/<option value="([^"]+)"/', $select[0] ?? '', $found);
    return array_values(array_diff($found[1], ['All']));
  }

  /**
   * The pattern select offers the patterns that have logs.
   */
  public function testTypeOptionsAreThePatternsWithLogs(): void {
    $offered = $this->typeOptions();
    $this->assertSame([], $offered);
    $this->createLog('project_publish', ['created' => 100]);
    $this->createLog('project_apply', ['created' => 200]);
    // Not observable, disabled and unused patterns are not offered.
    $this->createLog('project_notify', ['created' => 300]);
    $this->createLog('feedback_off', ['created' => 400]);
    $offered = $this->typeOptions();
    sort($offered);
    $this->assertSame(['project_apply', 'project_publish'], $offered);
    $this->createLog('project_complete', ['created' => 500]);
    $offered = $this->typeOptions();
    sort($offered);
    $this->assertSame(['project_apply', 'project_complete', 'project_publish'], $offered);
  }

}
