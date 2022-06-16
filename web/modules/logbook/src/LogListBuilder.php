<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a list controller for the log entity type.
 */
final class LogListBuilder extends EntityListBuilder {

  /**
   * The view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $viewBuilder;

  /**
   * The pattern storage class.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $patternStorage;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected Request $request;

  /**
   * Constructs a new LectureListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder
   *   The view builder.
   * @param \Drupal\Core\Entity\EntityStorageInterface $pattern_storage
   *   The pattern storage class.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    EntityViewBuilderInterface $view_builder,
    EntityStorageInterface $pattern_storage,
    Request $request
  ) {
    parent::__construct($entity_type, $storage);
    $this->viewBuilder = $view_builder;
    $this->patternStorage = $pattern_storage;
    $this->request = $request;
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
      $container->get('entity_type.manager')->getViewBuilder('log'),
      $container->get('entity_type.manager')->getStorage($entity_type->getBundleEntityType()),
      $container->get('request_stack')->getCurrentRequest(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function render() {

    $build['options'] = [
      '#type' => 'container',
      '#prefix' => '<div class="claro-details"><div class="claro-details__wrapper">',
      '#suffix' => '</div></div>',
    ];

    if ($this->request->query->get('hidden')) {
      $build['options']['show_hidden'] = [
        '#markup' => Link::createFromRoute($this->t('Collapse hidden logs'), 'entity.log.collection')->toString(),
      ];
    }
    else {
      $build['options']['show_hidden'] = [
        '#markup' => Link::createFromRoute($this->t('Show hidden logs'), 'entity.log.collection', ['hidden' => 1])->toString(),
      ];
    }

    $build['table'] = [
      '#prefix' => '<div class="system-status-general-info__items clearfix">',
      '#cache' => [
        'contexts' => $this->entityType->getListCacheContexts(),
        'tags' => $this->entityType->getListCacheTags(),
      ],
    ];

    foreach ($this->load() as $entity) {
      if ($row = $this->viewBuilder->view($entity)) {
        $build['table'][] = $row;
      }
    }

    $build['table']['#suffix'] = '</div>';

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $properties = [
      'status' => TRUE,
      'detectable' => TRUE,
    ];
    if (!$this->request->query->get('hidden')) {
      $properties['hidden'] = FALSE;
    }
    $detectable_log_patterns = $this->patternStorage
      ->loadByProperties($properties);
    if (!empty($detectable_log_patterns)) {
      $query = $this->getStorage()->getQuery()
        ->condition('type', array_map(fn($p) => $p->id(), $detectable_log_patterns), 'IN')
        ->accessCheck(TRUE)
        ->sort($this->entityType->getKey('id'), 'DESC');
      // Only add the pager if a limit is specified.
      if ($this->limit) {
        $query->pager($this->limit);
      }
      return $query->execute();
    }
    return [];
  }

}
