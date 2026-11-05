<?php

namespace Drupal\Tests\manager\ExistingSite;

use Drupal\Core\File\FileExists;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\creatives\Entity\Creative;
use Drupal\file\Entity\File;
use Drupal\projects\ProjectState;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the creatives finder view and its filters.
 */
#[Group('manager')]
class CreativesViewTest extends ManagerExistingSiteTestBase {

  /**
   * The ids of the creatives this test created.
   *
   * @var int[]
   */
  protected array $creativeIds = [];

  /**
   * The manager that runs the view.
   */
  protected ?Creative $viewer = NULL;

  /**
   * Only staff open the creatives page.
   */
  public function testAccess(): void {
    $access = $this->container->get('access_manager');
    $accounts = [
      'anonymous' => [new AnonymousUserSession(), FALSE],
      'creative' => [$this->createCreative(), FALSE],
      'organization' => [$this->createOrganization(), FALSE],
      'manager' => [$this->createManager(), TRUE],
      'supervisor' => [$this->createSupervisor(), TRUE],
    ];
    foreach ($accounts as $label => [$account, $expected]) {
      $this->assertSame($expected, $access->checkNamedRoute('view.creatives.page', [], $account), $label);
    }
  }

  /**
   * The view lists active creatives, not blocked ones or organizations.
   */
  public function testListsActiveCreativesOnly(): void {

    $prefix = $this->randomMachineName();
    $active = $this->creative(['field_name' => $prefix . ' Active']);
    $blocked = $this->creative(['field_name' => $prefix . ' Blocked']);
    $blocked->block()->save();
    $organization = $this->createOrganization();
    $organization->set('field_name', $prefix . ' Organization')->save();
    $this->creativeIds[] = (int) $organization->id();

    $this->assertSame([(int) $active->id()], $this->uids(['name' => $prefix]));
  }

  /**
   * The text filters match parts of the name, the mail and the city.
   */
  public function testTextFilters(): void {

    $prefix = $this->randomMachineName();
    $hamburg = $this->creative([
      'field_name' => $prefix . ' Hamburg',
      'field_city' => 'Hamburg',
    ]);
    $koeln = $this->creative([
      'field_name' => $prefix . ' Köln',
      'field_city' => 'Köln',
    ]);
    $nowhere = $this->creative(['field_name' => $prefix . ' Nowhere']);

    $this->assertSame([(int) $koeln->id()], $this->uids(['name' => $prefix . ' Kö']));
    $mail = $nowhere->getEmail();
    $this->assertSame([(int) $nowhere->id()], $this->uids(['mail' => substr($mail, 2, 6)]));
    $this->assertSame([(int) $hamburg->id()], $this->uids([
      'name' => $prefix,
      'city_op' => 'contains',
      'city' => 'mburg',
    ]));

    // A creative without a city doesn't live in Hamburg either.
    $result = $this->uids([
      'name' => $prefix,
      'city_op' => 'not',
      'city' => 'Hamburg',
    ]);
    $this->assertEqualsCanonicalizing([(int) $koeln->id(), (int) $nowhere->id()], $result);
  }

  /**
   * The skills match any or all, the tags include and exclude.
   */
  public function testTermFilters(): void {

    $skills = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'skills')
      ->condition('status', 1)
      ->sort('tid')
      ->range(0, 2)
      ->execute();
    $this->assertCount(2, $skills);
    [$skill_a, $skill_b] = array_values(array_map('intval', $skills));

    $with_a = $this->creative(['field_skills' => [$skill_a]]);
    $with_b = $this->creative(['field_skills' => [$skill_b]]);
    $with_both = $this->creative(['field_skills' => [$skill_a, $skill_b]]);
    $this->creative();

    $or = $this->uids(['skills_op' => 'or', 'skills' => [$skill_a, $skill_b]]);
    $this->assertEqualsCanonicalizing([(int) $with_a->id(), (int) $with_b->id(), (int) $with_both->id()], $or);
    $and = $this->uids(['skills_op' => 'and', 'skills' => [$skill_a, $skill_b]]);
    $this->assertSame([(int) $with_both->id()], $and);

    $t1 = $this->createTag();
    $t2 = $this->createTag();
    $t3 = $this->createTag();
    $only_t1 = $this->creative(['field_tags' => [$t1->id()]]);
    $this->creative(['field_tags' => [$t1->id(), $t2->id()]]);
    $t1_and_t3 = $this->creative(['field_tags' => [$t1->id(), $t3->id()]]);

    $result = $this->uids([
      'tags' => $this->autocomplete($t1),
      'tags_not' => $this->autocomplete($t2),
    ]);
    $this->assertEqualsCanonicalizing([(int) $only_t1->id(), (int) $t1_and_t3->id()], $result);

    // Two matching tags list the creative once.
    $result = $this->uids(['tags' => $this->autocomplete($t1, $t3)]);
    $this->assertCount(1, array_keys($result, (int) $t1_and_t3->id(), TRUE));
    $this->assertCount(3, $result);
  }

  /**
   * The activity and profile filters.
   */
  public function testActivityAndProfileFilters(): void {

    $prefix = $this->randomMachineName();
    $old = $this->creative([
      'field_name' => $prefix . ' Old',
      'created' => strtotime('2015-06-01'),
      'access' => strtotime('2016-06-01'),
    ]);
    $new = $this->creative([
      'field_name' => $prefix . ' New',
      'created' => strtotime('2022-06-01'),
      'access' => strtotime('2023-06-01'),
    ]);
    $never = $this->creative([
      'field_name' => $prefix . ' Never',
      'created' => strtotime('2022-06-01'),
      'access' => 0,
      'field_about' => ['value' => 'Hello', 'format' => 'plain_text'],
      'field_avatar' => $this->createImage(),
      'field_newsletter' => 1,
    ]);
    $no_newsletter = $this->creative([
      'field_name' => $prefix . ' NoNewsletter',
      'created' => strtotime('2015-06-01'),
      'access' => strtotime('2023-06-01'),
      'field_newsletter' => 0,
    ]);
    // Never set: the field table holds no row.
    $old->set('field_newsletter', NULL)->save();
    $new->set('field_newsletter', NULL)->save();

    $this->assertEqualsCanonicalizing([(int) $new->id(), (int) $never->id()], $this->uids([
      'name' => $prefix,
      'registered_op' => '>=',
      'registered' => ['value' => '2020-01-01'],
    ]));
    // Never is before any date.
    $this->assertEqualsCanonicalizing([(int) $old->id(), (int) $never->id()], $this->uids([
      'name' => $prefix,
      'last_access_op' => '<',
      'last_access' => ['value' => '2020-01-01'],
    ]));

    // Group 1 is "Yes", group 2 is "No".
    $this->assertSame([(int) $never->id()], $this->uids(['name' => $prefix, 'never_logged_in' => 1]));
    $this->assertNotContains((int) $never->id(), $this->uids(['name' => $prefix, 'never_logged_in' => 2]));

    $this->assertSame([(int) $never->id()], $this->uids(['name' => $prefix, 'has_avatar' => 1]));
    $this->assertNotContains((int) $never->id(), $this->uids(['name' => $prefix, 'has_avatar' => 2]));
    $this->assertCount(3, $this->uids(['name' => $prefix, 'has_avatar' => 2]));
    $this->assertSame([(int) $never->id()], $this->uids(['name' => $prefix, 'has_about' => 1]));
    $this->assertCount(3, $this->uids(['name' => $prefix, 'has_about' => 2]));

    $this->assertSame([(int) $never->id()], $this->uids(['name' => $prefix, 'newsletter' => '1']));
    $this->assertEqualsCanonicalizing(
      [(int) $old->id(), (int) $new->id(), (int) $no_newsletter->id()],
      $this->uids(['name' => $prefix, 'newsletter' => '0']),
    );
  }

  /**
   * The project activity and the portfolio link filters.
   */
  public function testProjectAndLinkFilters(): void {

    $prefix = $this->randomMachineName();
    $a = $this->creative(['field_name' => $prefix . ' A']);
    $b = $this->creative(['field_name' => $prefix . ' B']);
    $c = $this->creative(['field_name' => $prefix . ' C']);
    $d = $this->creative(['field_name' => $prefix . ' D']);

    $open = $this->createProject(ProjectState::Open)->appendApplicant($a)->appendApplicant($d);
    $open->save();
    $second = $this->createProject(ProjectState::Open)->appendApplicant($d);
    $second->save();
    $completed = $this->createProject(ProjectState::Completed)->appendApplicant($b)->appendParticipant($b);
    $completed->save();

    $this->assertEqualsCanonicalizing(
      [(int) $a->id(), (int) $b->id(), (int) $d->id()],
      $this->uids(['name' => $prefix, 'applied' => '1']),
    );
    $this->assertSame([(int) $c->id()], $this->uids(['name' => $prefix, 'applied' => '0']));
    $this->assertSame([(int) $b->id()], $this->uids(['name' => $prefix, 'participated' => '1']));
    $this->assertEqualsCanonicalizing(
      [(int) $a->id(), (int) $c->id(), (int) $d->id()],
      $this->uids(['name' => $prefix, 'participated' => '0']),
    );

    $prefix = $this->randomMachineName();
    $e = $this->creative(['field_name' => $prefix . ' E', 'field_url' => 'https://example.com']);
    $f = $this->creative(['field_name' => $prefix . ' F', 'field_portfolio' => 'https://example.org']);
    $g = $this->creative(['field_name' => $prefix . ' G']);
    $this->assertEqualsCanonicalizing(
      [(int) $e->id(), (int) $f->id()],
      $this->uids(['name' => $prefix, 'has_link' => '1']),
    );
    $this->assertSame([(int) $g->id()], $this->uids(['name' => $prefix, 'has_link' => '0']));
  }

  /**
   * Creates a creative with the given values.
   */
  protected function creative(array $values = []): Creative {
    $creative = $this->createCreative();
    foreach ($values as $name => $value) {
      $creative->set($name, $value);
    }
    $creative->save();
    $this->creativeIds[] = (int) $creative->id();
    return $creative;
  }

  /**
   * Creates a tag with a random valid name.
   */
  protected function createTag(): TermInterface {
    $term = Term::create([
      'vid' => 'tags',
      'name' => strtolower($this->randomMachineName()),
    ]);
    $term->save();
    $this->markEntityForCleanup($term);
    return $term;
  }

  /**
   * Formats terms as the input of an autocomplete field.
   */
  protected function autocomplete(TermInterface ...$terms): string {
    return implode(', ', array_map(static fn (TermInterface $term): string => $term->label() . ' (' . $term->id() . ')', $terms));
  }

  /**
   * Creates an image file for an avatar.
   */
  protected function createImage(): File {
    $uri = $this->container->get('file_system')->copy(
      DRUPAL_ROOT . '/core/tests/fixtures/files/image-1.png',
      'public://' . $this->randomMachineName() . '.png',
      FileExists::Rename,
    );
    $file = File::create(['uri' => $uri]);
    $file->setPermanent();
    $file->save();
    $this->markEntityForCleanup($file);
    return $file;
  }

  /**
   * Runs the page display as a manager with the given exposed input.
   *
   * @return int[]
   *   The listed ids of the creatives this test created, in result order.
   */
  protected function uids(array $input): array {
    $this->viewer ??= $this->createManager();
    $current_user = $this->container->get('current_user');
    $previous = $current_user->getAccount();
    $current_user->setAccount($this->viewer);
    try {
      $view = Views::getView('creatives');
      $this->assertInstanceOf(ViewExecutable::class, $view);
      $view->setDisplay('page');
      $view->setExposedInput($input);
      $view->setItemsPerPage(0);
      $view->execute();
    }
    finally {
      $current_user->setAccount($previous);
    }
    $uids = array_map(static fn ($row): int => (int) $row->_entity->id(), $view->result);
    return array_values(array_intersect($uids, $this->creativeIds));
  }

}
