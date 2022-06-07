<?php

namespace Drupal\projects\Plugin\Field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes referencing children of parent.
 */
class ComputedProjectReferenceFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function entityTypeManager() {
    if (!isset($this->entityTypeManager)) {
      $this->entityTypeManager = \Drupal::entityTypeManager();
    }
    return $this->entityTypeManager;
  }

  /**
   * Computes the field value.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    // Fetch the user and determine respective field.
    $account = $this->getEntity();
    $field = $account->bundle() == 'user' ? 'field_participants' : 'uid';

    // Query projects referencing user.
    $query = $this->entityTypeManager()
      ->getStorage('project')->getQuery()
      ->accessCheck(TRUE)
      ->condition($field, $account->id());
    $project_ids = $query->execute();

    // This kind of programming is sinful.
    // @todo Work out query access for project entities.
    $projects = $this->entityTypeManager()
      ->getStorage('project')
      ->loadMultiple($project_ids);
    $accessible_projects = [];
    foreach ($projects as $project) {
      if ($project->access('view')) {
        $accessible_projects[] = $project;
      }
    }

    foreach ($accessible_projects as $project) {
      /** @var \Drupal\youvo\Plugin\Field\FieldType\CacheableEntityReferenceItem $item */
      $item = $this->createItem(0, ['target_id' => $project->id()]);
      $item->getTargetIdProperty()->mergeCacheMaxAge(0);
      $this->list[] = $item;
    }
  }

}
