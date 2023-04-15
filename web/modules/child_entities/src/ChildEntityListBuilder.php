<?php

namespace Drupal\child_entities;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Committee meeting entities.
 *
 * @ingroup child_entity
 */
class ChildEntityListBuilder extends EntityListBuilder {

  use ChildEntityEnsureTrait;

  /**
   * The parent entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected EntityInterface $parent;

  /**
   * Constructs a ChildEntityListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The child entity type.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The child entity storage.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The child entity route match.
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    RouteMatchInterface $route_match
  ) {
    $this->entityImplementsChildEntityInterface($entity_type);
    parent::__construct($entity_type, $storage);
    $this->parent = $route_match->getParameter($entity_type->getKey('parent'));
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\Exception\UnsupportedEntityTypeDefinitionException
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type
  ) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $sort_by = $this->entityType->hasKey('weight') ?
      $this->entityType->getKey('weight') :
      $this->entityType->getKey('id');
    $query = $this->getStorage()->getQuery()
      ->accessCheck(FALSE)
      ->sort($sort_by)
      ->condition($this->entityType->getKey('parent'), $this->parent->id());

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * If this is a form, invalidate the parent cache with each form submit.
   *
   * This avoids problems, for example when saving weights for children.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $invalidate_tags[] = $this->parent->getEntityTypeId() . ':' . $this->parent->id();
    Cache::invalidateTags($invalidate_tags);
  }

}
