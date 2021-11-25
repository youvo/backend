<?php

namespace Drupal\child_entities;

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
  public function postCreate(EntityStorageInterface $storage) {

    // Invalidate cache for parent to recompute children field.
    $parent = $this->getParentEntity();
    if (isset($parent)) {
      $parent->save();
    }

    parent::postCreate($storage);
  }

  /**
   * Returns an array of base field definitions for publishing status.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to add the publishing status field to.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   An array of base field definitions.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function childBaseFieldDefinitions(EntityTypeInterface $entity_type) {
    ChildEntityEnsureTrait::entityImplementsChildEntityInterface($entity_type);
    return [
      $entity_type->getKey('parent') => BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Parent ID'))
        ->setSetting('target_type', $entity_type->getKey('parent'))
        ->setTranslatable(FALSE)
        ->setReadOnly(TRUE),
      $entity_type->getKey('weight') => BaseFieldDefinition::create('integer')
        ->setLabel(t('Weight'))
        ->setDescription(t('The weight of this term in relation to other terms.'))
        ->setDefaultValue(0),
    ];
  }

  /**
   * Checks whether entity is child entity.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The Child Entity Type.
   *
   * @return bool
   *   True if the Entity Type is a Child Entity.
   */
  private function isChildEntity(EntityTypeInterface $entity_type) {
    $original_class = $entity_type->getOriginalClass();
    if (in_array(ChildEntityTrait::class, class_uses($original_class))) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Build route parameters.
   *
   * @param array $uri_route_parameters
   *   The Child Route Parameters.
   * @param \Drupal\child_entities\ChildEntityInterface $parent_entity
   *   The Parent Entity.
   *
   * @return array
   *   The Parent Entity Route Parameters.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildParentParams(array $uri_route_parameters, ChildEntityInterface $parent_entity) {

    $uri_route_parameters[$parent_entity->getParentEntityTypeId()] = $parent_entity->getParentId();

    if ($parent_entity->isParentAnotherChildEntity()) {
      /** @var \Drupal\child_entities\ChildEntityInterface $grandparent_entity */
      $grandparent_entity = $parent_entity->getParentEntity();
      $uri_route_parameters = $this->buildParentParams($uri_route_parameters, $grandparent_entity);
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel) + [
      $this->getParentEntityTypeId() => $this->getParentId(),
    ];

    if ($this->isParentAnotherChildEntity()) {
      $uri_route_parameters = $this->buildParentParams($uri_route_parameters, $this->getParentEntity());
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntityTypeId() {
    if ($this->getEntityType()->hasKey('parent')) {
      return $this->getEntityType()->getKey('parent');
    }
    throw new \InvalidArgumentException(sprintf('"%s" key must be set in "entity_keys" of class "%s"', 'parent', get_class($this)));
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntityType() {
    return $this->entityTypeManager()
      ->getDefinition($this->getParentEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function isParentAnotherChildEntity() {
    return $this->isChildEntity($this->getParentEntityType());
  }

  /**
   * {@inheritdoc}
   */
  public function getParentId() {
    return $this->getEntityKey('parent');
  }

  /**
   * {@inheritdoc}
   */
  public function setParentId($uid) {
    $key = $this->getEntityType()->getKey('parent');
    $this->set($key, $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntity() {
    $key = $this->getEntityType()->getKey('parent');
    return $this->get($key)->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setParentEntity(EntityInterface $parent) {
    $key = $this->getEntityType()->getKey('parent');
    $this->set($key, $parent);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginEntity() {
    $child = $this;
    while ($child->isParentAnotherChildEntity()) {
      $child = $child->getParentEntity();
    }
    return $child->getParentEntity();
  }

  /**
   * Overwrite call toUrl for non-present canonical route.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function toUrl($rel = 'canonical', array $options = []) {
    if ($rel == 'canonical') {
      return Url::fromUri('route:<nolink>')->setOptions($options);
    }
    else {
      return parent::toUrl($rel, $options);
    }
  }

}
