<?php

namespace Drupal\child_entities;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Url;

/**
 * Provides a trait for parent information.
 */
trait ChildEntityTrait {

  use ChildEntityEnsureTrait;

  /**
   * {@inheritdoc}
   */
  abstract protected function entityTypeManager();

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    // Invalidate parent cache to update the computed children field.
    $this->invalidateParentCache();
  }

  /**
   * Returns an array of base field definitions for the parent and weight.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The child entity type to add the fields to.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   The base field definitions.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function childBaseFieldDefinitions(EntityTypeInterface $entity_type): array {
    self::entityImplementsChildEntityInterface($entity_type);
    return [
      $entity_type->getKey('parent') => BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Parent ID'))
        ->setSetting('target_type', $entity_type->getKey('parent'))
        ->setTranslatable(FALSE)
        ->setReadOnly(TRUE),
      $entity_type->getKey('weight') => BaseFieldDefinition::create('integer')
        ->setLabel(t('Weight'))
        ->setDescription(t('The weight of this entity in relation to its siblings.'))
        ->setDefaultValue(0),
    ];
  }

  /**
   * Checks whether entity is child entity.
   */
  private function isChildEntity(EntityTypeInterface $entity_type): bool {
    return $entity_type->entityClassImplements(ChildEntityInterface::class);
  }

  /**
   * {@inheritdoc}
   *
   * Adds the IDs of all ancestors as route parameters.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function urlRouteParameters($rel): array {
    $uri_route_parameters = parent::urlRouteParameters($rel) + [
      $this->getParentEntityTypeId() => $this->getParentId(),
    ];

    $child = $this;
    while ($child->isParentAnotherChildEntity()) {
      /** @var \Drupal\child_entities\ChildEntityInterface $child */
      $child = $child->getParentEntity();
      $uri_route_parameters += [$child->getParentEntityTypeId() => $child->getParentId()];
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntityTypeId(): string {
    if ($this->getEntityType()->hasKey('parent')) {
      return $this->getEntityType()->getKey('parent');
    }
    throw new \InvalidArgumentException(sprintf('"%s" key must be set in "entity_keys" of class "%s"', 'parent', get_class($this)));
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntityType(): ?EntityTypeInterface {
    return $this->entityTypeManager()
      ->getDefinition($this->getParentEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function isParentAnotherChildEntity(): bool {
    return $this->isChildEntity($this->getParentEntityType());
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId(): ?int {
    $parent_id = $this->getEntityKey('parent');
    if ($parent_id === NULL) {
      return NULL;
    }
    return (int) $parent_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentId(int $uid): static {
    $key = $this->getEntityType()->getKey('parent');
    $this->set($key, $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntity(): EntityInterface {
    $key = $this->getEntityType()->getKey('parent');
    return $this->get($key)->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentEntity(EntityInterface $parent): static {
    $key = $this->getEntityType()->getKey('parent');
    $this->set($key, $parent);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginEntity(): EntityInterface {
    $child = $this;
    while ($child->isParentAnotherChildEntity()) {
      $child = $child->getParentEntity();
    }
    return $child->getParentEntity();
  }

  /**
   * Overwrites toUrl method for non-present canonical route.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function toUrl($rel = 'canonical', array $options = []): Url {
    if ($rel === 'canonical') {
      return Url::fromUri('route:<nolink>')->setOptions($options);
    }
    return parent::toUrl($rel, $options);
  }

  /**
   * Invalidates the cache of the parent.
   */
  protected function invalidateParentCache(): void {
    $parent = $this->getParentEntity();
    $invalidate_tags[] = $parent->getEntityTypeId() . ':' . $parent->id();
    Cache::invalidateTags($invalidate_tags);
  }

}
