<?php

namespace Drupal\child_entities;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a trait for parent information.
 */
trait ChildEntityTrait {

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
   *   Thrown when the entity type does not implement EntityPublishedInterface
   *   or if it does not have a "published" entity key.
   */
  public static function childBaseFieldDefinitions(EntityTypeInterface $entity_type) {
    // @todo Extract this check to a function.
    if (!$entity_type->entityClassImplements(ChildEntityInterface::class)) {
      throw new UnsupportedEntityTypeDefinitionException(
        'The entity type ' . $entity_type->id() . ' does not implement \Drupal\child_entity\Entity\ChildEntityInterface.');
    }
    if (!$entity_type->hasKey('parent')) {
      throw new UnsupportedEntityTypeDefinitionException('The entity type ' . $entity_type->id() . ' does not have a "parent" entity key.');
    }

    return [
      $entity_type->getKey('parent') => BaseFieldDefinition::create('entity_reference')
        ->setLabel(new TranslatableMarkup('Parent ID'))
        ->setSetting('target_type', $entity_type->getKey('parent'))
        ->setTranslatable(FALSE)
        ->setReadOnly(TRUE)
        ->setDisplayOptions('view', [
          'type' => 'entity_reference_label',
          'label' => 'inline',
          'weight' => -3,
        ])
        ->setDisplayConfigurable('form', FALSE)
        ->setDisplayConfigurable('view', TRUE),
    ];
  }

  /**
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
   * Provide details the missing key.
   *
   * @param string $key
   *   The Key that is missing.
   */
  private function reportMissingKey(string $key) {
    throw new \InvalidArgumentException(sprintf('"%s" key must be set in "entity_keys" of class "%s"', $key, get_class($this)));
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
    $this->reportMissingKey('parent');
  }

  /**
   * {@inheritdoc}
   */
  public function getParentEntityType() {
    return \Drupal::entityTypeManager()
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

}
