<?php

namespace Drupal\Tests\manager\ExistingSite;

use Behat\Mink\Element\NodeElement;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the layout of the filter form of the creatives finder.
 */
#[Group('manager')]
class CreativesFormTest extends ManagerExistingSiteTestBase {

  /**
   * The summaries of the filter groups, in their order.
   */
  const array GROUPS = ['Skills and tags', 'Activity', 'Profile'];

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
    $session->elementExists('xpath', $this->groupXpath('Activity') . '//input[@name="registered[value]" and @type="date"]');
    $session->elementExists('xpath', $this->groupXpath('Activity') . '//input[@name="last_access[value]" and @type="date"]');
    $session->elementExists('xpath', $this->groupXpath('Profile') . '//*[@name="newsletter"]');
    $session->elementExists('xpath', $this->groupXpath('Profile') . '//label[normalize-space() = "Newsletter"]');
    $session->elementExists('xpath', $this->groupXpath('Activity') . '//legend[normalize-space() = "Registered"]');

    $session->elementNotExists('css', 'form.views-exposed-form [name="city_op"]');
    $this->assertSame(['before', 'after'], $this->optionLabels('registered_op'));
    $this->assertSame(['before', 'after'], $this->optionLabels('last_access_op'));
    $session->elementNotExists('css', 'form.views-exposed-form [name="skills_op"]');
    $session->elementExists('xpath', $this->groupXpath('Skills and tags') . '//label/span[@class="skill" and @data-skill="design" and @title="Design"]');
    $session->elementExists('xpath', $this->groupXpath('Skills and tags') . '//input[@name="tags" and contains(@class, "creatives-tags")]');
    $session->elementNotExists('css', 'form.views-exposed-form [name="tags_not"]');
  }

  /**
   * A group with an active filter is open.
   */
  public function testGroupOpensWithActiveFilter(): void {
    $this->drupalGet('creatives', ['query' => ['newsletter' => '0', 'registered' => ['value' => '']]]);
    $this->assertTrue($this->group('Profile')->hasAttribute('open'));
    $this->assertFalse($this->group('Activity')->hasAttribute('open'));
    $this->assertFalse($this->group('Skills and tags')->hasAttribute('open'));
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
    $this->assertTrue($this->group('Skills and tags')->hasAttribute('open'));

    $this->drupalGet('creatives', ['query' => ['name' => $prefix, 'skills' => [$skill]]]);
    $session->pageTextContains($with);
    $session->pageTextNotContains($without);
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
    return "//form[contains(@class, 'views-exposed-form')]//details[summary[normalize-space() = '" . $summary . "']]";
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
   * Returns the option labels of a select of the filter form.
   *
   * @return string[]
   *   The labels in their order.
   */
  protected function optionLabels(string $name): array {
    $select = $this->assertSession()->elementExists('css', 'form.views-exposed-form select[name="' . $name . '"]');
    return array_map(
      fn (NodeElement $option): string => trim($option->getText()),
      $select->findAll('css', 'option'),
    );
  }

}
