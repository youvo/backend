<?php

namespace Drupal\paragraphs;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of paragraph type entities.
 *
 * @see \Drupal\paragraphs\Entity\ParagraphType
 */
class ParagraphTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['title'] = $this->t('Paragraph type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t(
      'No paragraph types available. <a href=":link">Add paragraph type</a>.',
      [':link' => Url::fromRoute('entity.paragraph_type.add_form')->toString()]
    );
    return $build;
  }

}
