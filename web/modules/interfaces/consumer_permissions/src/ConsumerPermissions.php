<?php

namespace Drupal\consumer_permissions;

use Drupal\consumers\ConsumerStorage;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides methods to handle consumer permissions.
 */
class ConsumerPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  const PREFIX = 'authorize_with_client_';

  /**
   * Constructs a ConsumerPermissions instance.
   *
   * @param \Drupal\consumers\ConsumerStorage $consumerStorage
   *   The consumer storage.
   */
  public function __construct(protected ConsumerStorage $consumerStorage) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('consumer')
    );
  }

  /**
   * Generates permissions for all consumers.
   */
  public function generate(): array {
    foreach ($this->consumerStorage->loadMultiple() as $client) {
      $permissions[self::PREFIX . $client->id()] = [
        'title' => $this->t('Authorize with client %label', ['%label' => $client->label()]),
        'dependencies' => [$client->getConfigDependencyKey() => [$client->getConfigDependencyName()]],
      ];
    }
    return $permissions ?? [];
  }

}
