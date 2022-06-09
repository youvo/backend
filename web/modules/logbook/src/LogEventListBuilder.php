<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for the log event entity type.
 */
final class LogEventListBuilder extends EntityListBuilder {

  /**
   * The log event view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $logEventViewBuilder;

  /**
   * Constructs a new LectureListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityViewBuilderInterface $log_event_view_builder
   *   The log event view builder.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    EntityViewBuilderInterface $log_event_view_builder
  ) {
    parent::__construct($entity_type, $storage);
    $this->logEventViewBuilder = $log_event_view_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type
  ) {
    return new self(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')->getViewBuilder('log_event'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    $build['table']['#prefix'] = '<div class="system-status-general-info__items clearfix">';
    foreach ($this->load() as $entity) {
      if ($row = $this->logEventViewBuilder->view($entity)) {
        $build['table'][] = $row;
      }
    }
    $build['table']['#suffix'] = '</div>';
    return $build;
  }

}
