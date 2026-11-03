<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Entity\LogPattern;
use Drupal\logbook\Entity\LogText;
use Drupal\organizations\Entity\Organization;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the logbook view.
 *
 * The tests change log patterns, need an empty logbook and assert the exact
 * order and totals of the list, so they are not ExistingSite tests.
 */
#[Group('logbook')]
#[RunTestsInSeparateProcesses]
class LogbookViewTest extends LogbookKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity'];

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
    // User 1 bypasses permission checks: use it up.
    $this->createUser();
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
  protected function renderPage(array $input = [], array $permissions = ['view observable log']): string {
    // The view checks access: render as a user who may see it.
    $this->setCurrentUser($this->createUser($permissions));
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $view->setExposedInput($input);
    $render = $view->buildRenderable('page_1');
    return (string) $this->container->get('renderer')->renderInIsolation($render);
  }

  /**
   * Creates a log and returns its id.
   */
  protected function logId(string $type, array $values = []): int {
    return (int) $this->createLog($type, $values)->id();
  }

  /**
   * Deletes all logs, so the next scenario starts with an empty logbook.
   */
  protected function deleteLogs(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('log');
    $storage->delete($storage->loadMultiple());
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
   * Creates a creative with a name.
   */
  protected function createNamedCreative(string $name): Creative {
    $creative = $this->createCreative();
    $creative->setName($name);
    $creative->save();
    return $creative;
  }

  /**
   * Gets the patterns that the pattern select of the page offers.
   *
   * @return list<string>
   *   The pattern ids, without the option for all, sorted.
   */
  protected function typeOptions(): array {
    preg_match('/<select[^>]*name="type".*?<\/select>/s', $this->renderPage(), $select);
    preg_match_all('/<option value="([^"]+)"/', $select[0] ?? '', $found);
    $options = array_values(array_diff($found[1], ['All']));
    sort($options);
    return $options;
  }

  /**
   * The pattern flags and the exposed filters decide what is listed.
   */
  public function testScopeAndTypeFilter(): void {

    $apply = $this->logId('project_apply', ['created' => 100]);
    $publish = $this->logId('project_publish', ['created' => 200]);
    $notify = $this->logId('project_notify', ['created' => 300]);
    $this->createLog('feedback_off', ['created' => 400]);

    // Not observable and disabled patterns are not listed, newest first.
    $this->assertSame([$publish, $apply], $this->ids());
    // The date column is not sortable: the list stays newest first.
    $this->assertSame([$publish, $apply], $this->ids(['order' => 'created', 'sort' => 'asc']));

    // The exposed pattern filter limits the list to one pattern.
    $this->assertSame([$apply], $this->ids(['type' => 'project_apply']));
    $this->assertSame([$publish], $this->ids(['type' => 'project_publish']));
    $this->assertSame([$publish, $apply], $this->ids(['type' => 'All']));

    // The list varies per permissions, so cached results are not shared.
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $view->initHandlers();
    $this->assertContains('user.permissions', $view->filter['log_pattern_scope']->getCacheContexts());

    // Making a pattern observable lists it, without any change of the view.
    $pattern = LogPattern::load('project_notify');
    $pattern->set('observable', TRUE)->save();
    $this->assertSame([$notify, $publish, $apply], $this->ids());
    // A pattern that is created later is listed as well.
    $this->createPattern('project_new', ['observable' => TRUE]);
    $new = $this->logId('project_new', ['created' => 500]);
    $this->assertContains($new, $this->ids());
    // Disabling a pattern removes its logs from the list.
    $pattern->set('status', FALSE)->save();
    $this->assertNotContains($notify, $this->ids());

    // Deleting a log pattern does not take the view with it. The delete form
    // of a config entity deletes the config that depends on it. The patterns
    // exist now: saving the view records what it depends on.
    View::load('logbook')->save();
    $changes = $this->container->get('config.manager')
      ->getConfigEntitiesToChangeOnDependencyRemoval('config', ['logbook.log_pattern.project_apply']);
    $deleted = array_map(static fn ($entity) => $entity->getConfigDependencyName(), $changes['delete']);
    $this->assertNotContains('views.view.logbook', $deleted);

    // A pattern outside the list cannot be reached through the filter. The
    // invalid choice flags the form as failed for the rest of the request, so
    // this comes last.
    $pattern->set('status', TRUE)->set('observable', FALSE)->save();
    $this->assertNotContains($notify, $this->ids(['type' => 'project_notify']));
    $this->assertNotContains($notify, $this->ids(['type' => 'bogus']));
  }

  /**
   * The created filter includes the end day; the pager has 50 rows, 5 links.
   */
  public function testCreatedRangeAndPager(): void {

    $this->createLog('project_apply', ['created' => strtotime('2026-06-01 12:00')]);
    $start = $this->createLog('project_apply', ['created' => strtotime('2026-06-05 00:30')]);
    $in = $this->createLog('project_apply', ['created' => strtotime('2026-06-10 12:00')]);
    $end = $this->createLog('project_apply', ['created' => strtotime('2026-06-15 23:30')]);
    $this->createLog('project_apply', ['created' => strtotime('2026-06-16 00:30')]);
    $this->createLog('project_apply', ['created' => strtotime('2026-06-20 12:00')]);
    $range = ['created' => ['min' => '2026-06-05', 'max' => '2026-06-15']];
    $this->assertSame([(int) $end->id(), (int) $in->id(), (int) $start->id()], $this->ids($range));
    $this->deleteLogs();

    for ($i = 1; $i <= 351; $i++) {
      $this->createLog('project_apply', ['created' => $i]);
    }
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $view->execute();
    $this->assertCount(50, $view->result);
    $this->assertSame(351, (int) $view->pager->getTotalItems());
    // The pager links at most five pages, so it fits on a phone.
    preg_match_all('#class="pager__item(?: is-active)? ?"#', $this->renderPage(), $items);
    $this->assertCount(5, $items[0]);
  }

  /**
   * The page, detectable and archived logs and hidden projects need access.
   */
  public function testPermissions(): void {

    // The page needs the permission to view observable logs. Administering
    // the log patterns is not the permission of the logbook.
    $access_manager = $this->container->get('access_manager');
    $this->container->get('router.builder')->rebuild();
    $this->assertFalse($access_manager->checkNamedRoute('view.logbook.page_1', [], $this->createUser([])));
    $this->assertFalse($access_manager->checkNamedRoute('view.logbook.page_1', [], $this->createUser(['administer log pattern'])));
    $this->assertTrue($access_manager->checkNamedRoute('view.logbook.page_1', [], $this->createUser(['view observable log'])));

    // Managers view observable logs only; supervisors and observers also
    // view detectable logs.
    $this->createPattern('project_decline', ['detectable' => TRUE]);
    $apply = $this->logId('project_apply', ['created' => 100]);
    $decline = $this->logId('project_decline', ['created' => 200]);
    $this->setCurrentUser($this->createUser(['view observable log']));
    $this->assertSame([$apply], $this->ids());
    $this->setCurrentUser($this->createUser(['view observable log', 'view detectable log']));
    $this->assertSame([$decline, $apply], $this->ids());
    $this->deleteLogs();

    // Archived logs are listed only with the permission, in the view and in
    // access-checked entity queries.
    $active = $this->logId('project_apply', ['created' => 100]);
    $archived = $this->createLog('project_apply', ['created' => 200])->setArchived();
    $archived->save();
    $archived = (int) $archived->id();
    $query = fn () => array_map('intval', array_values(
      $this->container->get('entity_type.manager')->getStorage('log')->getQuery()->accessCheck(TRUE)->sort('id')->execute()
    ));
    $this->setCurrentUser($this->createUser(['view observable log']));
    $this->assertSame([$active], $this->ids());
    $this->assertSame([$active], $query());
    $this->setCurrentUser($this->createUser(['view observable log', 'view archived log']));
    $this->assertSame([$archived, $active], $this->ids());
    $this->assertSame([$active, $archived], $query());
    $this->deleteLogs();

    // Logs of hidden projects are listed only beyond the public level.
    $visible_project = $this->createLogbookProject('Visible');
    $visible = $this->logId('project_publish', ['created' => 100, 'project' => $visible_project->id()]);
    $hidden_project = $this->createLogbookProject('Hidden');
    $hidden_project->setHidden()->save();
    $hidden = $this->logId('project_publish', ['created' => 200, 'project' => $hidden_project->id()]);
    $without_project = $this->logId('project_publish', ['created' => 50]);
    $this->setCurrentUser($this->createUser(['view public log']));
    $this->assertSame([$visible, $without_project], $this->ids());
    // JSON:API collections use entity queries, not Views.
    $this->assertEqualsCanonicalizing([$visible, $without_project], $query());
    $this->setCurrentUser($this->createUser(['view public log', 'view observable log']));
    $this->assertSame([$hidden, $visible, $without_project], $this->ids());
    $hidden_project->setHidden(FALSE)->save();
    $this->setCurrentUser($this->createUser(['view public log']));
    $this->assertSame([$hidden, $visible, $without_project], $this->ids());
  }

  /**
   * A text tag matches the texts, titles and names of a log.
   */
  public function testTextSearch(): void {

    // A text tag matches the message, the project title and the organization.
    // The wildcards % and _ match literally.
    $project = $this->createLogbookProject('Zebra Garden');
    $with_project = $this->logId('project_apply', ['project' => $project->id(), 'created' => 100]);
    $percent = $this->logId('project_apply', ['message' => 'Rabatt 50% heute', 'created' => 200]);
    $other = $this->logId('project_apply', ['message' => 'Rabatt 500 heute', 'created' => 300]);
    $organization = $this->createNamedOrganization('Kranich e.V.');
    $with_organization = $this->logId('project_apply', ['organization' => $organization->id(), 'created' => 400]);
    $this->assertSame([$with_project], $this->ids(['tags' => ['text:Zebra']]));
    $this->assertSame([$percent], $this->ids(['tags' => ['text:50%']]));
    $this->assertSame([], $this->ids(['tags' => ['text:Rabatt_50']]));
    $this->assertContains($other, $this->ids(['tags' => ['text:Rabatt']]));
    $this->assertSame([$with_organization], $this->ids(['tags' => ['text:kranich']]));

    // Cached results follow renamed projects and organizations.
    $both = $this->logId('project_apply', [
      'project' => $project->id(),
      'organization' => $organization->id(),
      'created' => 500,
    ]);
    $project->setTitle('Anderer Titel')->save();
    $this->assertSame([], $this->ids(['tags' => ['text:Zebra']]));
    $this->assertSame([$both, $with_organization], $this->ids(['tags' => ['text:Kranich']]));
    $organization->set('field_name', 'Storch e.V.')->save();
    $this->assertSame([], $this->ids(['tags' => ['text:Kranich']]));
    $this->deleteLogs();

    // A text tag matches the names of the people in a log, in every role.
    $creative = $this->createNamedCreative('Monika Großmann');
    $manager = $this->createNamedCreative('Fredolina Schütte');
    $author = $this->createNamedCreative('Willy Schreiber');
    $member = $this->logId('project_apply', ['creatives' => [$creative->id()], 'created' => 100]);
    $managed = $this->logId('project_apply', ['manager' => $manager->id(), 'created' => 200]);
    $written = $this->logId('project_apply', ['author' => $author->id(), 'created' => 300]);
    $this->createLog('project_apply', ['created' => 400]);
    $this->assertSame([$member], $this->ids(['tags' => ['text:großmann']]));
    $this->assertSame([$managed], $this->ids(['tags' => ['text:Schütte']]));
    $this->assertSame([$written], $this->ids(['tags' => ['text:Schreiber']]));
    $this->deleteLogs();

    // A text tag matches the log text of the pattern, not its placeholders.
    $apply_text = LogText::create([
      'log_pattern' => 'project_apply',
      'text' => '%Creative hat für das Projekt %Project Interesse bekundet!',
    ]);
    $apply_text->save();
    $publish_text = LogText::create([
      'log_pattern' => 'project_publish',
      'text' => 'Das Projekt %Project wurde veröffentlicht.',
    ]);
    $publish_text->save();
    $apply = $this->logId('project_apply', ['created' => 100]);
    $publish = $this->logId('project_publish', ['created' => 200]);
    $this->assertSame([$apply], $this->ids(['tags' => ['text:interesse']]));
    $this->assertSame([$publish], $this->ids(['tags' => ['text:veröffentlicht']]));
    $this->assertSame([$publish, $apply], $this->ids(['tags' => ['text:Projekt']]));
    $this->assertSame([], $this->ids(['tags' => ['text:Creative']]));
    $this->assertSame([], $this->ids(['tags' => ['text:%Project']]));
    // Cached results follow edited log texts.
    $apply_text->setText('Beworben')->save();
    $this->assertSame([], $this->ids(['tags' => ['text:Interesse']]));
    $apply_text->delete();
    $publish_text->delete();
    $this->deleteLogs();

    // Typing an organization name finds the logs of its projects, as its tag.
    $this->createPattern('feedback_complete', ['observable' => TRUE]);
    $organization = $this->createNamedOrganization('Kranich e.V.');
    $project = $this->createLogbookProject('Flug');
    $project->setOwnerId($organization->id())->save();
    $feedback = $this->logId('feedback_complete', ['project' => $project->id(), 'created' => 100]);
    $this->createLog('feedback_complete', ['project' => $this->createLogbookProject('Andere')->id(), 'created' => 200]);
    $this->assertSame([$feedback], $this->ids(['tags' => ['text:Kranich']]));
    $this->deleteLogs();

    // Text tags narrow the result, each of them.
    $project = $this->createLogbookProject('Kranichflug');
    $feedback = $this->logId('project_apply', [
      'project' => $project->id(),
      'message' => 'Feedback kam',
      'created' => 100,
    ]);
    $this->createLog('project_apply', ['project' => $project->id(), 'message' => 'Nichts', 'created' => 200]);
    $this->createLog('project_apply', ['message' => 'Feedback woanders', 'created' => 300]);
    $this->assertSame([$feedback], $this->ids(['tags' => ['project:' . $project->id(), 'text:Feedback']]));
    $both = $this->logId('project_apply', ['message' => 'Zebra im Garden', 'created' => 400]);
    $this->createLog('project_apply', ['message' => 'Zebra allein', 'created' => 500]);
    $this->assertSame([$both], $this->ids(['tags' => ['text:Zebra', 'text:Garden']]));
  }

  /**
   * The log text names the role of the author, the manager of the log too.
   */
  public function testLogTextNamesAuthorRole(): void {

    $this->createPattern('project_role', [
      'observable' => TRUE,
      'tokens' => [['token' => '%AuthorWithRole', 'required' => '1']],
    ]);
    LogText::create(['log_pattern' => 'project_role', 'text' => '%AuthorWithRole schreibt.'])->save();
    $creative = $this->createCreative();
    $manager = $this->createCreative();
    $organization = $this->createNamedOrganization('Kranich e.V.');
    $role = fn (array $values): string => strip_tags($this->createLog('project_role', $values)->getMarkup());

    $this->assertStringStartsWith('creative ', $role(['author' => $creative->id()]));
    $this->assertStringStartsWith('manager ', $role(['author' => $manager->id(), 'manager' => $manager->id()]));
    $this->assertStringStartsWith('organization ', $role(['author' => $organization->id()]));
    $this->assertStringEndsWith(' schreibt.', $role(['author' => $creative->id()]));
  }

  /**
   * Person, organization and project tags match every role and add up.
   */
  public function testTagSearch(): void {

    // A person or organization tag matches every role in a log.
    $creative = $this->createCreative();
    $organization = $this->createNamedOrganization('Kranich e.V.');
    $author = $this->logId('project_apply', ['author' => $creative->id(), 'created' => 100]);
    $member = $this->logId('project_apply', ['creatives' => [$creative->id()], 'created' => 200]);
    $managed = $this->logId('project_apply', ['manager' => $creative->id(), 'created' => 300]);
    $owned = $this->logId('project_apply', ['organization' => $organization->id(), 'created' => 400]);
    $this->createLog('project_apply', ['created' => 500]);
    $this->assertSame([$managed, $member, $author], $this->ids(['tags' => ['user:' . $creative->id()]]));
    $this->assertSame([$owned], $this->ids(['tags' => ['user:' . $organization->id()]]));
    $this->deleteLogs();

    // An organization tag finds the logs of its projects without
    // organization.
    $this->createPattern('feedback_complete', ['observable' => TRUE]);
    $project = $this->createLogbookProject('Kranichflug');
    $feedback = $this->logId('feedback_complete', ['project' => $project->id(), 'created' => 100]);
    $this->createLog('feedback_complete', ['project' => $this->createLogbookProject()->id(), 'created' => 200]);
    $this->assertSame([$feedback], $this->ids(['tags' => ['user:' . $project->getOwnerId()]]));
    $this->deleteLogs();

    // Tags of people, organizations and projects add up.
    $by_creative = $this->logId('project_apply', ['creatives' => [$creative->id()], 'created' => 100]);
    $of_project = $this->logId('project_apply', ['project' => $project->id(), 'created' => 200]);
    $this->createLog('project_apply', ['created' => 300]);
    $tags = ['user:' . $creative->id(), 'project:' . $project->id()];
    $this->assertSame([$of_project, $by_creative], $this->ids(['tags' => $tags]));
    $this->deleteLogs();

    // A log with two tagged creatives is listed once.
    $second = $this->createCreative();
    $log = $this->logId('project_apply', ['creatives' => [$creative->id(), $second->id()], 'created' => 100]);
    $this->assertSame([$log], $this->ids(['tags' => ['user:' . $creative->id(), 'user:' . $second->id()]]));

    // Invalid tags and ids of deleted entities are ignored, as in the bar.
    $with_message = $this->logId('project_apply', ['message' => 'Kranich hallo', 'created' => 200]);
    $all = $this->ids();
    $this->assertSame([$with_message, $log], $all);
    $this->assertSame($all, $this->ids(['tags' => ['node:1', 'user:abc', 'text:  ']]));
    $this->assertSame($all, $this->ids(['tags' => 'garbage']));
    $this->assertSame($all, $this->ids(['tags' => ['user:999999']]));
    $this->assertSame([$with_message], $this->ids(['tags' => ['user:999999', 'text:Kranich']]));
  }

  /**
   * The exposed form, the empty texts and the offered patterns.
   */
  public function testExposedFormAndEmptyTexts(): void {

    // An empty logbook without a search has nothing to reset.
    $html = $this->renderPage();
    $this->assertStringContainsString('Noch keine Einträge.', $html);
    $this->assertStringNotContainsString('setze die Suche zurück', $html);
    // The search bar replaces the text box.
    $this->assertStringContainsString('logbook-search', $html);
    $this->assertStringContainsString('/logbook/search/autocomplete', $html);
    $this->assertStringNotContainsString('Suchen …', $html);
    // The search bar comes first, then the pattern and the date range.
    preg_match_all('/<(?:input|select)[^>]*name="([^"]+)"/', substr($html, 0, strpos($html, '<table') ?: NULL), $matches);
    $names = array_values(array_diff($matches[1], ['form_build_id', 'form_id']));
    $this->assertSame(['tags', 'type', 'created[min]', 'created[max]'], $names);
    // Without logs, no pattern is offered.
    $this->assertSame([], $this->typeOptions());

    // Invalid input does not show up in the search bar.
    $html = $this->renderPage(['tags' => ['node:1', '<b>x</b>']]);
    $this->assertStringNotContainsString('node:1', $html);
    $this->assertStringNotContainsString('&lt;b&gt;x', $html);

    // The date range uses date inputs and the pattern select says what all
    // means. The end day is made inclusive in the form values, not in the
    // field.
    $html = $this->renderPage(['created' => ['min' => '2026-06-05', 'max' => '2026-06-15']]);
    $this->assertMatchesRegularExpression('/<input[^>]*type="date"[^>]*name="created\[min\]"/', $html);
    $this->assertMatchesRegularExpression('/<input[^>]*type="date"[^>]*name="created\[max\]"/', $html);
    $this->assertStringContainsString('value="2026-06-05"', $html);
    $this->assertStringContainsString('value="2026-06-15"', $html);
    $this->assertStringNotContainsString('23:59:59', $html);
    $this->assertStringContainsString('All types', $html);
    $this->assertStringNotContainsString('- Any -', $html);

    // The pattern select offers the patterns that have logs. Not observable,
    // disabled and unused patterns are not offered.
    $this->createLog('project_publish', ['created' => 100]);
    $this->createLog('project_apply', ['created' => 200]);
    $this->createLog('project_notify', ['created' => 300]);
    $this->createLog('feedback_off', ['created' => 400]);
    $this->assertSame(['project_apply', 'project_publish'], $this->typeOptions());
    $this->createLog('project_complete', ['created' => 500]);
    $this->assertSame(['project_apply', 'project_complete', 'project_publish'], $this->typeOptions());

    // A search without results says so and points to the reset button.
    $html = $this->renderPage(['tags' => ['text:zzzzqqq']]);
    $this->assertStringContainsString('Keine Einträge für diese Suche.', $html);
    $this->assertStringContainsString('setze die Suche zurück', $html);
    $html = $this->renderPage(['type' => 'project_apply', 'tags' => ['text:zzzzqqq']]);
    $this->assertStringContainsString('Keine Einträge für diese Suche.', $html);
  }

  /**
   * The rows show the tone, the message and the visibility of each log.
   */
  public function testRenderedRows(): void {

    $this->createPattern('project_mediate', ['observable' => TRUE, 'public' => TRUE]);
    // Logs without project or organization are listed and render.
    $this->createLog('project_apply', ['message' => 'Hello <b>world</b>', 'created' => 100]);
    $this->createLog('project_mediate', ['created' => 200]);
    $this->createLog('project_publish', ['created' => 300]);
    $html = $this->renderPage();

    // Cells: tone pill, escaped message, visibility icons.
    $this->assertStringContainsString('class="logbook-type" data-tone="positive"', $html);
    $this->assertStringContainsString('logbook-event-message', $html);
    $this->assertStringContainsString('Hello &lt;b&gt;world&lt;/b&gt;', $html);
    // The visibility column has an accessible name, but no visible title.
    $this->assertStringContainsString('Sichtbarkeit</th>', $html);
    $this->assertStringContainsString('aria-label="Internal: only managers of the organization"', $html);
    // Internal logs show a lock, public logs a globe, promoted logs a star
    // only.
    preg_match_all('/<tr>.*?<\/tr>/s', $html, $rows);
    $icons = [];
    foreach ($rows[0] as $row) {
      if (preg_match('/logbook-type[^>]*>([^<]+)</', $row, $type)) {
        preg_match_all('/data-icon="(\w+)"/', $row, $found);
        $icons[$type[1]] = $found[1];
      }
    }
    $this->assertSame(['lock'], $icons['Apply']);
    $this->assertSame(['globe'], $icons['Mediate']);
    $this->assertSame(['star'], $icons['Publish']);
    // The date column is not sortable.
    $this->assertStringNotContainsString('Sort ascending', $html);
    $this->assertStringNotContainsString('order=created', $html);
    // Only a celebrate pattern colors the row.
    $this->assertStringNotContainsString('logbook-row-celebrate', $html);

    $this->createLog('project_complete', ['created' => 400]);
    $view = Views::getView('logbook');
    $view->setDisplay('page_1');
    $render = $view->buildRenderable('page_1');
    $html = (string) $this->container->get('renderer')->renderInIsolation($render);
    $this->assertSame(1, substr_count($html, 'logbook-row-celebrate'));
    $this->assertContains('logbook/table', $render['#attached']['library'] ?? []);
    // Editing a log pattern invalidates the rendered list.
    $this->assertContains('config:log_pattern_list', $render['#cache']['tags'] ?? []);
    $this->deleteLogs();

    // An archived log shows the archive icon instead of its visibility.
    $this->createLog('project_publish', ['created' => 100])->setArchived()->save();
    $html = $this->renderPage([], ['view observable log', 'view archived log']);
    preg_match_all('/data-icon="(\w+)"/', $html, $found);
    $this->assertSame(['archive'], $found[1]);
  }

}
