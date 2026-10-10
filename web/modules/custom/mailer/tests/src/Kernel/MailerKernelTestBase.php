<?php

namespace Drupal\Tests\mailer\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mailer_policy\Entity\MailerPolicy;
use Drupal\mailer_transport\Entity\Transport;
use Drupal\Tests\mailer\Traits\MailCaptureTrait;
use Drupal\Tests\projects\Kernel\EventSubscriber\ProjectEventSubscriberTestBase;

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

}
