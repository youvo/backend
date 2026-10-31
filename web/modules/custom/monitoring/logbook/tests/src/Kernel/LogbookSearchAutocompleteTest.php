<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Controller\LogbookSearchAutocompleteController;
use Drupal\logbook\SearchTags;
use Drupal\organizations\Entity\Organization;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the tag tokens and the autocomplete of the logbook search.
 *
 * @group logbook
 */
class LogbookSearchAutocompleteTest extends LogbookKernelTestBase {

  use UserCreationTrait;

  /**
   * Tokens are validated, deduplicated and capped.
   */
  public function testParse(): void {
    $parsed = SearchTags::parse([
      'user:3',
      'project:7',
      'user:3',
      'text: Feed back ',
      'node:1',
      'user:abc',
      'user:0',
      'user:',
      'text:  ',
      ['user:5'],
    ]);
    $this->assertSame(['user' => [3], 'project' => [7], 'text' => ['Feed back']], $parsed);
    $this->assertSame(['user' => [], 'project' => [], 'text' => []], SearchTags::parse(NULL));
    $this->assertSame(['user' => [4], 'project' => [], 'text' => []], SearchTags::parse('user:4'));
    $many = array_map(static fn (int $i) => 'user:' . $i, range(1, 25));
    $this->assertCount(20, SearchTags::parse($many)['user']);
    $long = SearchTags::parse(['text:' . str_repeat('a', 150)]);
    $this->assertSame(100, mb_strlen($long['text'][0]));
  }

  /**
   * The typed text comes first, then people, organizations and projects.
   */
  public function testMatchesUsersOrganizationsAndProjects(): void {
    $creative = $this->createNamedCreative('Anna Berg');
    $organization = $this->createNamedOrganization('Berg e.V.');
    $project = $this->createLogbookProject('Bergwanderung');
    $this->createNamedCreative('Someone Else');

    $items = $this->autocomplete(['q' => 'berg']);
    $this->assertSame([
      'value' => 'text:berg',
      'entity_id' => 'text:berg',
      'label' => 'berg',
      'info_label' => 'Text',
      'class' => 'logbook-search__text',
    ], $items[0]);
    $rest = array_slice($items, 1);
    $this->assertSame([
      'user:' . $creative->id(),
      'user:' . $organization->id(),
      'project:' . $project->id(),
    ], array_column($rest, 'entity_id'));
    $this->assertSame(array_column($rest, 'entity_id'), array_column($rest, 'value'));
    $this->assertSame(['Anna Berg', 'Berg e.V.', 'Bergwanderung'], array_column($rest, 'label'));
    $this->assertSame(['Person', 'Organisation', 'Projekt'], array_column($rest, 'info_label'));
  }

  /**
   * Selected tags are left out, short queries only offer the text.
   */
  public function testSelectedAndShortQuery(): void {
    $this->createNamedCreative('Bob');
    $project = $this->createLogbookProject('Bergwanderung');
    $this->assertSame(['text:b'], array_column($this->autocomplete(['q' => 'b']), 'entity_id'));
    $this->assertSame([], $this->autocomplete(['q' => '  ']));
    $items = $this->autocomplete(['q' => 'berg', 'selected' => 'project:' . $project->id() . ',text:berg']);
    $this->assertSame([], $items);
  }

  /**
   * At most ten entities are suggested, besides the text.
   */
  public function testLimit(): void {
    foreach (range(1, 12) as $i) {
      $this->createNamedCreative('Muster ' . $i);
    }
    $this->assertCount(11, $this->autocomplete(['q' => 'muster']));
  }

  /**
   * Only staff who can see the logbook get suggestions.
   */
  public function testRequiresPermission(): void {
    // The first user is the super user, who may do anything.
    $this->createUser();
    $access = $this->container->get('access_manager');
    $this->assertFalse($access->checkNamedRoute('logbook.search_autocomplete', [], $this->createUser([])));
    $this->assertTrue($access->checkNamedRoute('logbook.search_autocomplete', [], $this->createUser(['view observable log'])));
  }

  /**
   * Calls the autocomplete and decodes the answer.
   */
  protected function autocomplete(array $query): array {
    $controller = LogbookSearchAutocompleteController::create($this->container);
    $response = $controller->handle(new Request($query));
    return json_decode((string) $response->getContent(), TRUE);
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
   * Creates an organization with a name.
   */
  protected function createNamedOrganization(string $name): Organization {
    $organization = Organization::create(['name' => $this->randomMachineName(), 'field_name' => $name]);
    $organization->save();
    return $organization;
  }

}
