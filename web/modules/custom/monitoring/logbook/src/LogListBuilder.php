<?php

namespace Drupal\logbook;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a list controller for the log entity type.
 */
final class LogListBuilder extends EntityListBuilder {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {

    $build['options'] = [
      '#type' => 'container',
      '#prefix' => '<div class="claro-details"><div class="claro-details__wrapper">',
      '#suffix' => '</div></div>',
    ];

    if ($this->requestStack->getCurrentRequest()->query->get('hidden')) {
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
      if ($row = $this->entityTypeManager->getViewBuilder('log')->view($entity)) {
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
    if (!$this->requestStack->getCurrentRequest()->query->get('hidden')) {
      $properties['hidden'] = FALSE;
    }
    $detectable_log_patterns = $this->entityTypeManager
      ->getStorage($this->entityType->getBundleEntityType())
      ->loadByProperties($properties);
    if (!empty($detectable_log_patterns)) {
      $query = $this->getStorage()->getQuery()
        ->condition('type', array_map(static fn($p) => $p->id(), $detectable_log_patterns), 'IN')
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
