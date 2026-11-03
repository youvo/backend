<?php

namespace Drupal\Tests\logbook\ExistingSite;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\creatives\Entity\Creative;
use Drupal\logbook\Controller\LogbookSearchAutocompleteController;
use Drupal\logbook\Element\LogbookSearchTags;
use Drupal\logbook\SearchTags;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\ProjectInterface;
use Drupal\Tests\projects\ExistingSite\Resource\ProjectResourceTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the autocomplete and the search bar element of the logbook search.
 *
 * The names start with a random prefix, so the suggestions leave out the
 * people and projects of the site.
 */
#[Group('logbook')]
class LogbookSearchTest extends ProjectResourceTestBase {

  /**
   * A user id that does not exist.
   */
  protected const int MISSING_ID = 1999999999;

  /**
   * The typed text comes first, then people, organizations and projects.
   */
  public function testAutocompleteSuggestions(): void {

    $prefix = $this->randomMachineName();
    $creative = $this->createNamedCreative($prefix . ' Anna Berg');
    $organization = $this->createNamedOrganization($prefix . ' Berg e.V.');
    $project = $this->createNamedProject($prefix . 'wanderung');
    $this->createNamedCreative($this->randomMachineName() . ' Someone Else');

    $items = $this->autocomplete($prefix);
    $this->assertSame([
      'value' => 'text:' . $prefix,
      'entity_id' => 'text:' . $prefix,
      'label' => $prefix,
      'info_label' => 'Text',
      'class' => 'logbook-search-text',
    ], $items[0]);
    $rest = array_slice($items, 1);
    $this->assertSame([
      'user:' . $creative->id(),
      'user:' . $organization->id(),
      'project:' . $project->id(),
    ], array_column($rest, 'entity_id'));
    $this->assertSame(array_column($rest, 'entity_id'), array_column($rest, 'value'));
    $this->assertSame([$prefix . ' Anna Berg', $prefix . ' Berg e.V.', $prefix . 'wanderung'], array_column($rest, 'label'));
    $this->assertSame(['Person', 'Organisation', 'Projekt'], array_column($rest, 'info_label'));

    // Short queries only offer the text.
    $this->assertSame(['text:b'], array_column($this->autocomplete('b'), 'entity_id'));
    $this->assertSame([], $this->autocomplete('  '));

    // At most ten entities are suggested, besides the text.
    $prefix = $this->randomMachineName();
    foreach (range(1, 12) as $i) {
      $this->createNamedCreative($prefix . ' Muster ' . $i);
    }
    $this->assertCount(11, $this->autocomplete($prefix));
  }

  /**
   * Only staff who can see the logbook get suggestions.
   */
  public function testAutocompleteAccess(): void {
    $access = $this->container->get('access_manager');
    $this->assertFalse($access->checkNamedRoute('logbook.search_autocomplete', [], $this->createCreative()));
    $this->assertTrue($access->checkNamedRoute('logbook.search_autocomplete', [], $this->createManager()));
  }

  /**
   * Submitted tokens become the tags shown in the bar, in their order.
   */
  public function testSearchTagsValue(): void {

    $prefix = $this->randomMachineName();
    $organization = $this->createNamedOrganization($prefix . ' Berg e.V.');
    $project = $this->createNamedProject($prefix . 'wanderung');
    $tags = $this->value([
      'user:' . $organization->id(),
      'text:Feedback',
      'project:' . $project->id(),
      'user:' . static::MISSING_ID,
      'node:1',
    ]);
    $this->assertSame(['user:' . $organization->id(), 'text:Feedback', 'project:' . $project->id()], array_column($tags, 'entity_id'));
    $this->assertSame([$prefix . ' Berg e.V.', 'Feedback', $prefix . 'wanderung'], array_column($tags, 'label'));

    // Without a name, a person or organization is shown by its account name.
    $unnamed = $this->createOrganization();
    $this->assertSame([$unnamed->getAccountName()], array_column($this->value(['user:' . $unnamed->id()]), 'label'));

    // The JSON a Tagify input submits without its script is read as well.
    $json = json_encode([['value' => 'text:Zebra', 'entity_id' => 'text:Zebra', 'label' => 'Zebra']]);
    $this->assertSame(['text:Zebra'], array_column($this->value($json), 'entity_id'));

    // Nothing valid gives an empty value, never the raw input.
    $element = [];
    $this->assertSame('', LogbookSearchTags::valueCallback($element, ['node:1'], new FormState()));
    $this->assertSame('', LogbookSearchTags::valueCallback($element, ['user:' . static::MISSING_ID], new FormState()));
    $this->assertNull(LogbookSearchTags::valueCallback($element, FALSE, new FormState()));
  }

  /**
   * The element renders a Tagify input fed by the logbook autocomplete.
   */
  public function testSearchTagsElement(): void {

    $form = $this->container->get('form_builder')->getForm(new class extends FormBase {

      /**
       * Gets the form id.
       */
      public function getFormId(): string {
        return 'logbook_search_tags_test';
      }

      /**
       * Builds a form with the search bar.
       */
      public function buildForm(array $form, FormStateInterface $form_state): array {
        $form['tags'] = ['#type' => 'logbook_search_tags'];
        return $form;
      }

      /**
       * Submits nothing.
       */
      public function submitForm(array &$form, FormStateInterface $form_state): void {}

    });
    $element = $form['tags'];
    $this->assertContains('tagify-widget', $element['#attributes']['class']);
    $this->assertContains('logbook-search', $element['#attributes']['class']);
    $this->assertStringContainsString('/logbook/search/autocomplete', $element['#attributes']['data-autocomplete-url']);
    $this->assertContains('tagify/default', $element['#attached']['library']);
    $this->assertContains('logbook/search', $element['#attached']['library']);
    // The bar is named for screen readers and speaks German.
    $this->assertSame('Logbuch durchsuchen: Person, Organisation, Projekt oder Text', $element['#attributes']['aria-label']);
    $this->assertSame('Keine Vorschläge für:', $element['#attached']['drupalSettings']['tagify']['information_message']['no_matching_suggestions']);
    // The bar takes as many tags as the search honors.
    $this->assertSame(SearchTags::MAX_TOKENS, $element['#attributes']['data-cardinality']);
    $this->assertSame('Höchstens so viele Suchbegriffe:', $element['#attached']['drupalSettings']['tagify']['information_message']['limit_tag']);
  }

  /**
   * Calls the autocomplete and decodes the answer.
   */
  protected function autocomplete(string $text): array {
    $controller = LogbookSearchAutocompleteController::create($this->container);
    $response = $controller->handle(new Request(['q' => $text]));
    return json_decode((string) $response->getContent(), TRUE);
  }

  /**
   * Gets the tags of the search bar for the input.
   */
  protected function value(mixed $input): array {
    $element = [];
    $value = LogbookSearchTags::valueCallback($element, $input, new FormState());
    return json_decode((string) $value, TRUE);
  }

  /**
   * Creates a creative with a name.
   */
  protected function createNamedCreative(string $name): Creative {
    $creative = $this->createCreative();
    $creative->setName($name)->save();
    return $creative;
  }

  /**
   * Creates an organization with a name.
   */
  protected function createNamedOrganization(string $name): Organization {
    $organization = $this->createOrganization();
    $organization->set('field_name', $name)->save();
    return $organization;
  }

  /**
   * Creates a project with a title.
   */
  protected function createNamedProject(string $title): ProjectInterface {
    $project = $this->createProject();
    $project->setTitle($title)->save();
    return $project;
  }

}
