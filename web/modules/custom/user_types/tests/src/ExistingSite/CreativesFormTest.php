<?php

namespace Drupal\Tests\user_types\ExistingSite;

use Behat\Mink\Element\NodeElement;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the layout of the filter form of the creatives finder.
 */
#[Group('user_types')]
class CreativesFormTest extends CreativesTestBase {

  /**
   * The summaries of the filter groups, in their order.
   */
  const array GROUPS = ['Activity', 'Profile'];

  /**
   * Logs in a manager.
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->createManager());
  }

  /**
   * The form shows the main filters and closed groups with readable labels.
   */
  public function testGroupsAndLabels(): void {

    $this->drupalGet('creatives');
    $session = $this->assertSession();

    $summaries = array_map(
      fn (NodeElement $summary): string => trim($summary->getText()),
      $this->getSession()->getPage()->findAll('css', 'form.views-exposed-form details > summary'),
    );
    $this->assertSame(static::GROUPS, $summaries);
    foreach (static::GROUPS as $group) {
      $this->assertFalse($this->group($group)->hasAttribute('open'), $group);
    }
    foreach (['name', 'mail', 'city'] as $name) {
      $session->elementExists('css', 'form.views-exposed-form [name="' . $name . '"]');
      $session->elementNotExists('css', 'form.views-exposed-form details [name="' . $name . '"]');
    }
    foreach (['registered', 'last_access'] as $date) {
      $session->elementExists('xpath', $this->groupXpath('Activity') . '//input[@name="' . $date . '[value]" and @type="date"]');
      $this->assertSame(['after', 'before'], $this->radioLabels($date . '_op'));
    }
    $session->elementExists('xpath', $this->groupXpath('Profile') . '//*[@name="newsletter"]');
    $session->elementExists('xpath', $this->groupXpath('Profile') . '//legend[normalize-space() = "Newsletter"]');
    $session->elementExists('xpath', $this->groupXpath('Activity') . '//legend[normalize-space() = "Registered"]');

    $session->elementNotExists('css', 'form.views-exposed-form [name="city_op"]');
    foreach (['applied', 'participated', 'returned', 'accessed', 'has_avatar', 'jobs', 'newsletter'] as $name) {
      $this->assertSame(['Any', 'Yes', 'No'], $this->radioLabels($name), $name);
    }
    $session->elementNotExists('css', 'form.views-exposed-form [name="skills_op"]');
    $session->elementExists('css', 'form.views-exposed-form label > span.skill[data-skill="design"][title="Design"]');
    $session->elementNotExists('css', 'form.views-exposed-form details .creatives-skills');
    $session->elementExists('xpath', $this->groupXpath('Profile') . '//input[@name="tags" and contains(@class, "creatives-tags")]');
    $session->elementNotExists('css', 'form.views-exposed-form [name="tags_not"]');
  }

  /**
   * Filled-in fields stand out, Apply is primary and Reset a quiet link.
   */
  public function testActiveFieldsAndButtons(): void {

    $this->drupalGet('creatives', [
      'query' => [
        'name' => 'x',
        'registered_op' => '<',
        'registered' => ['value' => '2026-01-01'],
      ],
    ]);
    $session = $this->assertSession();

    $session->elementExists('css', 'input[name="name"].creatives-filter-active');
    $session->elementNotExists('css', 'input[name="mail"].creatives-filter-active');
    $session->elementExists('css', 'input[name="registered[value]"].creatives-filter-active');
    // Without a date, "after" and "before" filter nothing and stay quiet.
    $session->elementExists('css', 'fieldset.creatives-segmented-quiet input[name="last_access_op"]');
    $session->elementNotExists('css', 'fieldset.creatives-segmented-quiet input[name="registered_op"]');
    $session->elementExists('css', 'form.views-exposed-form input[type="submit"][value="Apply"].button--primary');
    $session->elementExists('css', 'form.views-exposed-form input[type="submit"][value="Reset"].creatives-reset');

    foreach (['Avatar', 'About text', 'Portfolio', 'Open to jobs'] as $label) {
      $session->elementExists('xpath', $this->groupXpath('Profile') . '//legend[normalize-space() = "' . $label . '"]');
    }
  }

  /**
   * A group with active filters is open and counts them in its summary.
   */
  public function testGroupOpensWithActiveFilter(): void {

    $this->drupalGet('creatives', [
      'query' => [
        'newsletter' => '0',
        'jobs' => '1',
        'registered' => ['value' => ''],
      ],
    ]);
    $session = $this->assertSession();

    $profile = $this->group('Profile');
    $this->assertTrue($profile->hasAttribute('open'));
    $this->assertStringContainsString('creatives-filter-group--active', (string) $profile->getAttribute('class'));
    $session->elementTextEquals('xpath', $this->groupXpath('Profile') . '/summary//span[contains(@class, "creatives-filter-count")]', '2 active');

    $activity = $this->group('Activity');
    $this->assertFalse($activity->hasAttribute('open'));
    $session->elementNotExists('xpath', $this->groupXpath('Activity') . '/summary//span[contains(@class, "creatives-filter-count")]');
  }

  /**
   * The skills checkboxes filter the creatives, as do links to the old list.
   */
  public function testSkillsFilterStillWorks(): void {

    $skill = (int) current($this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'skills')
      ->condition('status', 1)
      ->sort('tid')
      ->range(0, 1)
      ->execute());
    $this->assertGreaterThan(0, $skill);
    $prefix = $this->randomMachineName();
    $with = $prefix . ' With';
    $without = $prefix . ' Without';
    $this->createCreative()->set('field_name', $with)->set('field_skills', [$skill])->save();
    $this->createCreative()->set('field_name', $without)->save();
    $session = $this->assertSession();

    $this->drupalGet('creatives');
    $this->submitForm(['name' => $prefix], 'Apply');
    $session->pageTextContains($with);
    $session->pageTextContains($without);

    $this->drupalGet('creatives');
    $page = $this->getSession()->getPage();
    $page->fillField('name', $prefix);
    $page->checkField('edit-skills-' . $skill);
    $page->pressButton('Apply');
    $session->pageTextContains($with);
    $session->pageTextNotContains($without);
    $session->checkboxChecked('edit-skills-' . $skill);

    $this->drupalGet('creatives', ['query' => ['name' => $prefix, 'skills' => [$skill]]]);
    $session->pageTextContains($with);
    $session->pageTextNotContains($without);
  }

  /**
   * Name, mail and city suggest values of active creatives to staff only.
   */
  public function testTextFieldsSuggestCreatives(): void {

    $prefix = $this->randomMachineName();
    $this->createCreative()->set('field_name', $prefix . ' Creative')->set('field_city', $prefix . 'stadt')->save();
    $this->createOrganization()->set('field_name', $prefix . ' Organization')->save();
    $blocked = $this->createCreative();
    $blocked->set('field_name', $prefix . ' Blocked')->block()->save();
    $session = $this->assertSession();

    $this->drupalGet('views-autocomplete-filters/creatives/page/field_name_value/0', ['query' => ['q' => $prefix]]);
    $names = array_column(json_decode($this->getSession()->getPage()->getContent(), TRUE), 'value');
    $this->assertSame([$prefix . ' Creative'], $names);

    $this->drupalGet('views-autocomplete-filters/creatives/page/field_city_value/0', ['query' => ['q' => $prefix]]);
    $cities = array_column(json_decode($this->getSession()->getPage()->getContent(), TRUE), 'value');
    $this->assertSame([$prefix . 'stadt'], $cities);

    $this->drupalLogin($this->createCreative());
    $this->drupalGet('views-autocomplete-filters/creatives/page/field_name_value/0', ['query' => ['q' => $prefix]]);
    $session->statusCodeEquals(403);
  }

  /**
   * Reset in the profiles mode stays in the profiles mode.
   */
  public function testResetKeepsMode(): void {
    $this->drupalGet('creatives', ['query' => ['mode' => 'profiles', 'name' => 'x']]);
    $this->getSession()->getPage()->pressButton('Reset');
    $url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('mode=profiles', $url);
    $this->assertStringNotContainsString('name=x', $url);
    $this->assertSession()->elementNotExists('css', 'table');
  }

  /**
   * Returns the XPath of the filter group with a summary.
   */
  protected function groupXpath(string $summary): string {
    return "//form[contains(@class, 'views-exposed-form')]//details[summary[starts-with(normalize-space(), '" . $summary . "')]]";
  }

  /**
   * Returns the filter group with a summary.
   */
  protected function group(string $summary): NodeElement {
    $group = $this->getSession()->getPage()->find('xpath', $this->groupXpath($summary));
    $this->assertNotNull($group, $summary);
    return $group;
  }

  /**
   * Gets the labels of the radios of a filter, in their order.
   *
   * @return string[]
   *   The labels.
   */
  protected function radioLabels(string $name): array {
    $labels = [];
    foreach ($this->getSession()->getPage()->findAll('css', 'form.views-exposed-form input[type="radio"][name="' . $name . '"]') as $radio) {
      $label = $this->assertSession()->elementExists('css', 'label[for="' . $radio->getAttribute('id') . '"]');
      $labels[] = trim($label->getText());
    }
    return $labels;
  }

}
