<?php

namespace Drupal\Tests\manager\ExistingSite;

use Behat\Mink\Element\NodeElement;
use Drupal\creatives\Entity\Creative;
use Drupal\taxonomy\Entity\Term;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the profile cards of the creatives finder.
 */
#[Group('manager')]
class CreativeCardTest extends ManagerExistingSiteTestBase {

  /**
   * The card shows the main section of the profile, but no contact data.
   */
  public function testCardShowsTheMainSection(): void {

    $skills = $this->container->get('entity_type.manager')
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'skills')
      ->condition('status', 1)
      ->range(0, 1)
      ->execute();
    $this->assertCount(1, $skills);
    $tag = Term::create([
      'vid' => 'tags',
      'name' => strtolower($this->randomMachineName()),
    ]);
    $tag->save();
    $this->markEntityForCleanup($tag);

    $name = $this->randomMachineName();
    $about = $this->randomMachineName();
    $url = 'https://example.org/' . strtolower($this->randomMachineName());
    $phone = '+49 40 ' . random_int(1000000, 9999999);
    $creative = $this->createCreative();
    $creative->set('field_name', $name)
      ->set('field_position', 'Designerin')
      ->set('field_city', 'Leipzig')
      ->set('field_education', 'Kunsthochschule')
      ->set('field_about', ['value' => $about, 'format' => 'plain_text'])
      ->set('field_skills', array_values($skills))
      ->set('field_tags', [$tag->id()])
      ->set('field_url', $url)
      ->set('field_phone', $phone)
      ->save();

    $this->drupalLogin($this->createManager());
    $this->drupalGet('creatives', ['query' => ['mode' => 'profiles', 'name' => $name]]);

    $card = $this->card($creative);
    $link = $card->find('xpath', '//a[substring(@href, string-length(@href) - string-length("/user/' . $creative->id() . '") + 1) = "/user/' . $creative->id() . '"]');
    $this->assertNotNull($link);
    $this->assertSame($name, trim($link->getText()));
    $text = $card->getText();
    $this->assertStringContainsString('Designerin', $text);
    $this->assertStringContainsString('Leipzig', $text);
    $this->assertStringContainsString($about, $text);
    $this->assertStringContainsString((string) $tag->label(), $text);
    $this->assertStringContainsString($url, $card->getHtml());
    $this->assertStringNotContainsString($phone, $this->getSession()->getPage()->getContent());
  }

  /**
   * A card without an avatar shows the initials of the name.
   */
  public function testCardWithoutAvatarShowsInitials(): void {
    $creative = $this->createCreative();
    $creative->set('field_name', 'Max Bauer')->save();
    $this->drupalLogin($this->createManager());
    $this->drupalGet('creatives', ['query' => ['mode' => 'profiles', 'name' => 'Max Bauer']]);
    $this->assertStringContainsString('MB', $this->card($creative)->getText());
  }

  /**
   * Finds the card that links to the profile of a creative.
   */
  protected function card(Creative $creative): NodeElement {
    $path = '/user/' . $creative->id();
    $card = $this->getSession()->getPage()->find('xpath', '//article[contains(concat(" ", normalize-space(@class), " "), " user-profile-card ")][.//a[substring(@href, string-length(@href) - string-length("' . $path . '") + 1) = "' . $path . '"]]');
    $this->assertNotNull($card, 'No card for ' . $path);
    return $card;
  }

}
