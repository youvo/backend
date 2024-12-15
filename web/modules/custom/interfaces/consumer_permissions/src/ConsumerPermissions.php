<?php

namespace Drupal\consumer_permissions;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides methods to handle consumer permissions.
 */
class ConsumerPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  const PREFIX = 'authorize with client ';

  /**
   * Constructs a ConsumerPermissions instance.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Generates permissions for all consumers.
   */
  public function generate(): array {
    foreach ($this->entityTypeManager->getStorage('consumer')->loadMultiple() as $client) {
      $permissions[self::PREFIX . $client->id()] = [
        'title' => $this->t('Authorize with client %label', ['%label' => $client->label()]),
        'dependencies' => [$client->getConfigDependencyKey() => [$client->getConfigDependencyName()]],
      ];
    }
    return $permissions ?? [];
  }

}
