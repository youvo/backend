<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\creatives\Entity\Creative;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mailer_policy\Entity\MailerPolicy;
use Drupal\mailer_transport\Entity\Transport;
use Drupal\projects\ProjectInterface;
use Drupal\projects\ProjectState;
use Drupal\Tests\mailer\Traits\MailCaptureTrait;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\UserInterface;

/**
 * Provides a test base for mailer kernel tests.
 */
abstract class MailerKernelTestBase extends ProjectEventSubscriberTestBase {

  use MailCaptureTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'child_entities',
    'content_translation',
    'language',
    'correspondence',
    'filter',
    'text',
    'options',
    'system',
    'youvo',
    'symfony_mailer',
    'mailer_policy',
    'mailer_transport',
    'mailer',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('correspondence');
    $this->createUserFields();
    $this->installConfig([
      'system',
      'filter',
      'symfony_mailer',
      'mailer_policy',
      'mailer_transport',
      'correspondence',
      'mailer',
    ]);
    // The site identity is used for the sender and the From address.
    $this->config('system.site')->set('name', 'youvo')->set('mail', 'noreply@youvo.org')->save();
    // Use a transport that does not send.
    Transport::create(['id' => 'null', 'label' => 'Null', 'plugin' => 'null'])->save();
    $this->config('mailer_transport.settings')->set('default_transport', 'null')->save();
    $this->importPolicies();
  }

  /**
   * Creates the user fields that the mail variables read.
   *
   * The kernel base only enables the user type modules without their field
   * configuration.
   */
  protected function createUserFields(): void {
    $bundles = [
      'user' => ['field_name', 'field_phone'],
      'organization' => [
        'field_name',
        'field_contact',
        'field_phone',
        'field_street',
        'field_zip',
        'field_city',
        'field_country',
      ],
    ];
    foreach ($bundles as $bundle => $names) {
      foreach ($names as $name) {
        $this->createUserField($name, $bundle);
      }
    }
    $this->createUserField('field_manager', 'organization', 'entity_reference', ['target_type' => 'user']);
  }

  /**
   * Creates a user field on a bundle, reusing an existing storage.
   */
  protected function createUserField(string $name, string $bundle, string $type = 'string', array $settings = []): void {
    $storage = FieldStorageConfig::loadByName('user', $name) ?? FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'user',
      'type' => $type,
      'settings' => $settings,
    ]);
    if ($storage->isNew()) {
      $storage->save();
    }
    if (!FieldConfig::loadByName('user', $bundle, $name)) {
      FieldConfig::create(['field_storage' => $storage, 'bundle' => $bundle])->save();
    }
  }

  /**
   * Imports all mailer policies from the exported configuration.
   */
  protected function importPolicies(): void {
    $directory = DRUPAL_ROOT . '/../config/sync';
    foreach (glob($directory . '/mailer_policy.mailer_policy.mailer.*.yml') ?: [] as $file) {
      $values = Yaml::decode((string) file_get_contents($file));
      unset($values['uuid'], $values['_core']);
      MailerPolicy::create($values)->save();
    }
  }

  /**
   * Makes the default transport fail on every send.
   */
  protected function useFailingTransport(): void {
    Transport::create([
      'id' => 'broken',
      'label' => 'Broken',
      'plugin' => 'dsn',
      'configuration' => ['dsn' => 'smtp://127.0.0.1:1'],
    ])->save();
    $this->config('mailer_transport.settings')->set('default_transport', 'broken')->save();
  }

  /**
   * Creates a saved project whose owner has a mail address.
   */
  protected function createMailableProject(ProjectState $state = ProjectState::Open): ProjectInterface {
    $project = $this->createProject($state);
    $organization = $project->getOwner();
    $organization->set('mail', 'organization@example.com')->set('field_contact', 'Olga Org')->save();
    $organization->getManager()->set('mail', 'manager@example.com')->set('field_name', 'Manu Manager')->save();
    $project->save();
    return $project;
  }

  /**
   * Loads the recorded correspondence of a project.
   *
   * @return array<int|string, \Drupal\correspondence\CorrespondenceInterface&\Drupal\Core\Entity\ContentEntityInterface>
   *   The entries.
   */
  protected function loadEntries(int|string $project_id): array {
    /** @var array<int|string, \Drupal\correspondence\CorrespondenceInterface&\Drupal\Core\Entity\ContentEntityInterface> $entries */
    $entries = $this->container->get('entity_type.manager')
      ->getStorage('correspondence')
      ->loadByProperties(['project' => $project_id]);
    return $entries;
  }

  /**
   * Creates a creative with a mail address.
   */
  protected function createMailableCreative(string $name, ?string $mail = NULL): Creative {
    $creative = Creative::create([
      'name' => $name,
      'field_name' => $name,
      'mail' => $mail ?? $name . '@example.com',
    ]);
    $creative->save();
    return $creative;
  }

  /**
   * Loads the recorded correspondence of a project grouped by subtype.
   *
   * @return array<string, array<int, \Drupal\correspondence\CorrespondenceInterface&\Drupal\Core\Entity\ContentEntityInterface>>
   *   The entries, keyed by the full subtype.
   */
  protected function loadEntriesBySubtype(int|string $project_id): array {
    $grouped = [];
    foreach ($this->loadEntries($project_id) as $entry) {
      $grouped[$entry->getSubtype()][] = $entry;
    }
    return $grouped;
  }

  /**
   * Gives a user a new role with exactly the given permissions.
   *
   * Roles assigned earlier are replaced.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user, saved with the new role.
   * @param string[] $permissions
   *   The permissions of the role.
   *
   * @return \Drupal\user\UserInterface
   *   The user.
   */
  protected function accountWith(UserInterface $user, array $permissions): UserInterface {
    $role = Role::create(['id' => $this->randomMachineName(), 'label' => $this->randomString()]);
    foreach ($permissions as $permission) {
      $role->grantPermission($permission);
    }
    $role->save();
    // Replace earlier roles so that the user has exactly these permissions.
    $user->set('roles', [$role->id()]);
    $user->save();
    return $user;
  }

}
