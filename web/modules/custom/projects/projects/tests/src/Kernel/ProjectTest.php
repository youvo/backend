<?php

namespace Drupal\Tests\projects\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\organizations\Entity\Organization;
use Drupal\projects\Entity\Project;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\youvo\Test\RequestTrait;
use Faker\Factory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests a base for project tests.
 *
 * @group projects
 */
class ProjectTest extends KernelTestBase {

  use RequestTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  // @codingStandardsIgnoreStart
  protected $strictConfigSchema = FALSE;
  // @codingStandardsIgnoreEnd

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'workflows',
    'lifecycle',
    'projects',
    'jsonapi',
    'jsonapi_extras',
    'jsonapi_obscurity',
    'jsonapi_include',
    'serialization',
    'youvo',
    'taxonomy',
    'content_translation',
    'language',
    'datetime',
    'field',
    'options',
    'file',
    'image',
    'text',
    'block',
    'filter',
    'menu_link_content',
    'link',
    'node',
    'path_alias',
    'rest',
    'user_types',
    'user_bundle',
    'creatives',
    'organizations',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setParameter('jsonapi_obscurity.prefix', '/12345');
    $container->setParameter('jsonapi.base_path', '/api');
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('de')->save();

    $this->installEntitySchema('user');
    $this->createOrganization();

    $this->installEntitySchema('taxonomy_term');
    $this->installConfig('user_bundle');
    $this->installConfig('user_types');
    $this->installConfig('creatives');
    $this->installConfig('organizations');
    $this->installConfig('filter');

    $this->installConfig('youvo');
    $this->installEntitySchema('project');
    $this->installEntitySchema('project_result');
    $this->installConfig('projects');

    // Create node with random information.
    $project = Project::create([
      'uid' => 1,
      'type' => 'project',
      'title' => 'Test Project',
      'body' => [
        'value' => 'Test',
        'summary' => 'Test',
      ],
      'langcode' => 'de',
      'status' => 1,
      'field_allowance' => 'Test',
      'field_contact' => 'Test',
      'field_workload' => 1,
      'field_city' => 'Test',
      'field_material' => 'Test',
      'field_appreciation' => 'Test',
      'field_image_copyright' => 'Test',
      'field_lifecycle' => 'open',
    ]);

    $project->save();
  }

  /**
   * Tests get request for projects collection.
   */
  public function testDoRequest(): void {

    $request = Request::create('/12345/api/projects');
    $this->doRequest($request);
    self::assertStringContainsString(
      'Test Project',
      $this->getRawContent()
    );
  }

  /**
   * Creates an organization.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createOrganization(): void {

    // Object for random content.
    $faker = Factory::create('de_DE');
    $faker->seed(96886);

    $values = [
      'field_name' => 'Test Organization e.V.',
      'field_short_name' => 'Organization',
      'field_url' => 'https://www.' . $faker->domainName(),
      'field_aim' => $faker->text(80),
      'field_about' => $faker->text(600),
      'field_count_volunteer' => $faker->numberBetween(5, 20),
      'field_count_fulltime' => $faker->numberBetween(1, 5),
      'field_contact' => $faker->lastName(),
      'field_phone' => $faker->phoneNumber(),
      'field_reachability' => $faker->text(80),
      'field_referral' => $faker->text(),
      'field_street' => $faker->streetAddress(),
      'field_zip' => $faker->postcode(),
      'field_city' => $faker->city(),
      'field_country' => $faker->optional(0.2)->country(),
      'field_budget' => $faker->text(),
      'field_publicity' => $faker->optional()->text(),
      'name' => 'test@example.com',
      'mail' => 'test@example.com',
      'type' => 'organization',
      'pass' => 'password',
      'status' => 1,
      'uid' => 1,
    ];

    // Create organization.
    $organization = Organization::create($values);
    $organization->addRole('organization');
    $organization->save();
  }

}
