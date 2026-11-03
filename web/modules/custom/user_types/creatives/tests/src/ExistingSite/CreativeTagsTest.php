<?php

namespace Drupal\Tests\creatives\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\creatives\Entity\Creative;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\youvo\Traits\RequestTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Tests the tags creatives describe their abilities with.
 */
#[Group('creatives')]
class CreativeTagsTest extends ExistingSiteBase {

  use RequestTrait;

  /**
   * A creative creates a tag via JSON:API and adds it to the profile.
   */
  public function testCreativeAddsTag(): void {

    $creative = $this->createCreative();
    $suffix = $this->randomMachineName();

    // The name is saved in the tag format.
    $response = $this->request('POST', '/api/taxonomy/tags', [
      'type' => 'tag',
      'attributes' => ['name' => "Online Marketing $suffix"],
    ], $creative);
    $this->assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    // JSON:API Include flattens the relationships into the data.
    $this->assertArrayHasKey('skills', Json::decode((string) $response->getContent())['data']);
    $tag = $this->loadTag('online-marketing-' . strtolower($suffix));

    // The same tag in another spelling is refused.
    $response = $this->request('POST', '/api/taxonomy/tags', [
      'type' => 'tag',
      'attributes' => ['name' => "online   marketing $suffix"],
    ], $creative);
    $this->assertSame(422, $response->getStatusCode());

    $response = $this->request('PATCH', '/api/users/' . $creative->uuid() . '/relationships/tags', [
      ['type' => 'tag', 'id' => $tag->uuid()],
    ], $creative);
    $this->assertSame(204, $response->getStatusCode(), (string) $response->getContent());
    $creative = Creative::load($creative->id());
    $this->assertSame([$tag->id()], array_column($creative->get('field_tags')->getValue(), 'target_id'));
  }

  /**
   * A tag may share its name with a skill, but needs letters or digits.
   */
  public function testTagNameValidation(): void {

    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $skills = $storage->loadByProperties(['vid' => 'skills', 'machine_name' => 'design']);
    $this->assertNotEmpty($skills);

    $tag = $storage->create(['vid' => 'tags', 'name' => 'Design']);
    $this->assertCount(0, $tag->validate());

    $tag = $storage->create(['vid' => 'tags', 'name' => '#+*']);
    $this->assertCount(1, $tag->validate());
  }

  /**
   * A creative has at most ten tags.
   */
  public function testTagLimit(): void {

    $tags = [];
    for ($i = 0; $i < 11; $i++) {
      $tags[] = $this->createTag();
    }
    $creative = $this->createCreative();

    $creative->set('field_tags', array_slice($tags, 0, 10));
    $this->assertCount(0, $creative->get('field_tags')->validate());

    $creative->set('field_tags', $tags);
    $this->assertCount(1, $creative->get('field_tags')->validate());
  }

  /**
   * Signed-in users see the tags of a creative, anonymous visitors don't.
   */
  public function testTagsVisibility(): void {
    $creative = $this->createCreative();
    $this->assertTrue($creative->get('field_tags')->access('view', $this->createCreative()));
    $this->assertFalse($creative->get('field_tags')->access('view', new AnonymousUserSession()));
  }

  /**
   * Sends a JSON:API request as the account.
   */
  protected function request(string $method, string $path, array $data, Creative $account): Response {
    $request = Request::create($path, $method, [], [], [], [], Json::encode(['data' => $data]));
    $request->headers->set('Content-Type', 'application/vnd.api+json');
    $request->headers->set('Accept', 'application/vnd.api+json');
    $this->authenticateRequest($request, $account);
    // Requests are handled in this process: reset the static access caches.
    $this->container->get('entity_type.manager')->getAccessControlHandler('user')->resetCache();
    return $this->doRequest($request);
  }

  /**
   * Loads a tag by name and registers it for cleanup.
   */
  protected function loadTag(string $name): TermInterface {
    $tags = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'tags', 'name' => $name]);
    $this->assertCount(1, $tags);
    $tag = reset($tags);
    $this->assertInstanceOf(TermInterface::class, $tag);
    $this->markEntityForCleanup($tag);
    return $tag;
  }

  /**
   * Creates a tag.
   */
  protected function createTag(): TermInterface {
    $tag = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')
      ->create(['vid' => 'tags', 'name' => $this->randomMachineName()]);
    assert($tag instanceof TermInterface);
    $tag->save();
    $this->markEntityForCleanup($tag);
    return $tag;
  }

  /**
   * Creates a creative.
   */
  protected function createCreative(): Creative {
    $creative = Creative::create([
      'name' => $this->randomMachineName(),
      'mail' => $this->randomMachineName() . '@example.com',
      'pass' => 'password',
      'status' => 1,
    ]);
    $creative->addRole('creative');
    $creative->save();
    $this->markEntityForCleanup($creative);
    return $creative;
  }

}
