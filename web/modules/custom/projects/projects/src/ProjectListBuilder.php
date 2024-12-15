<?php

namespace Drupal\projects;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of project entities.
 */
class ProjectListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->languageManager = $container->get('language_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    // Enable language column and filter if multiple languages are added.
    $header = [
      'title' => $this->t('Title'),
      'author' => [
        'data' => $this->t('Author'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'status' => $this->t('Status'),
      'changed' => [
        'data' => $this->t('Updated'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];
    if ($this->languageManager->isMultilingual()) {
      $header['language_name'] = [
        'data' => $this->t('Language'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ];
    }
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\projects\Entity\Project $entity */
    $row['title']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
      '#url' => $entity->toUrl(),
    ];
    $row['author']['data'] = [
      '#theme' => 'username',
      '#account' => $entity->getOwner(),
    ];
    $row['status'] = $entity->isPublished() ? $this->t('Published') : $this->t('Unpublished');
    $row['changed'] = $this->dateFormatter->format($entity->getChangedTime(), 'short');
    if ($this->languageManager->isMultilingual()) {
      $row['language_name'] = $this->languageManager->getLanguageName($entity->language()->getId());
    }
    $row['operations']['data'] = $this->buildOperations($entity);
    return $row + parent::buildRow($entity);
  }

}
