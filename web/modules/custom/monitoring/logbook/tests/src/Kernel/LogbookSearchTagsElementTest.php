<?php

namespace Drupal\Tests\logbook\Kernel;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\logbook\Element\LogbookSearchTags;
use Drupal\logbook\SearchTags;
use Drupal\organizations\Entity\Organization;

/**
 * Tests the search bar element of the logbook.
 *
 * @group logbook
 */
class LogbookSearchTagsElementTest extends LogbookKernelTestBase {

  /**
   * Submitted tokens become the tags shown in the bar, in their order.
   */
  public function testValueFromQueryTokens(): void {
    $organization = Organization::create(['name' => $this->randomMachineName(), 'field_name' => 'Berg e.V.']);
    $organization->save();
    $project = $this->createLogbookProject('Bergwanderung');
    $element = [];
    $value = LogbookSearchTags::valueCallback($element, [
      'user:' . $organization->id(),
      'text:Feedback',
      'project:' . $project->id(),
      'user:999999',
      'node:1',
    ], new FormState());
    $tags = json_decode((string) $value, TRUE);
    $this->assertSame(['user:' . $organization->id(), 'text:Feedback', 'project:' . $project->id()], array_column($tags, 'entity_id'));
    $this->assertSame(['Berg e.V.', 'Feedback', 'Bergwanderung'], array_column($tags, 'label'));
  }

  /**
   * Without a name, a person or organization is shown by its account name.
   */
  public function testUnnamedUserShowsAccountName(): void {
    $organization = Organization::create(['name' => 'kranich']);
    $organization->save();
    $element = [];
    $value = LogbookSearchTags::valueCallback($element, ['user:' . $organization->id()], new FormState());
    $this->assertSame(['kranich'], array_column(json_decode((string) $value, TRUE), 'label'));
  }

  /**
   * The JSON a Tagify input submits without its script is read as well.
   */
  public function testValueFromTagifyJson(): void {
    $element = [];
    $json = json_encode([['value' => 'text:Zebra', 'entity_id' => 'text:Zebra', 'label' => 'Zebra']]);
    $value = LogbookSearchTags::valueCallback($element, $json, new FormState());
    $this->assertSame(['text:Zebra'], array_column(json_decode((string) $value, TRUE), 'entity_id'));
  }

  /**
   * Nothing valid gives an empty value, never the raw input.
   */
  public function testEmptyValue(): void {
    $element = [];
    $this->assertSame('', LogbookSearchTags::valueCallback($element, ['node:1'], new FormState()));
    $this->assertSame('', LogbookSearchTags::valueCallback($element, ['user:999999'], new FormState()));
    $this->assertNull(LogbookSearchTags::valueCallback($element, FALSE, new FormState()));
  }

  /**
   * The element renders a Tagify input fed by the logbook autocomplete.
   */
  public function testRendersTagifyInputWithLogbookRoute(): void {
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

}
