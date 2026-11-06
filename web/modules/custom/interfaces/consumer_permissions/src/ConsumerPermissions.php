<?php

namespace Drupal\consumer_permissions;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oauth_grant\Consumers;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides methods to handle consumer permissions.
 */
class ConsumerPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  const string PREFIX = 'authorize with client ';

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
   *
   * The permissions are named after the consumer UUID, which is the same on
   * every environment. Permissions for the known consumers exist even where
   * the consumer does not, so the shared role config keeps them on every
   * environment, also when a role is saved outside a config import.
   */
  public function generate(): array {

    $permissions = [];
    foreach (Consumers::KNOWN as [$uuid, $label]) {
      $permissions[static::PREFIX . $uuid] = [
        'title' => $this->t('Authorize with client %label', ['%label' => $label]),
        'dependencies' => ['content' => ['consumer:consumer:' . $uuid]],
      ];
    }

    foreach ($this->entityTypeManager->getStorage('consumer')->loadMultiple() as $client) {
      $permissions[static::PREFIX . $client->uuid()] = [
        'title' => $this->t('Authorize with client %label', ['%label' => $client->label()]),
        'dependencies' => [$client->getConfigDependencyKey() => [$client->getConfigDependencyName()]],
      ];
    }

    return $permissions;
  }

}
