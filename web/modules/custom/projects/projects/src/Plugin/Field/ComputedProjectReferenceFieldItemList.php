<?php

namespace Drupal\projects\Plugin\Field;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computes referencing children of parent.
 *
 * @todo Use DI after https://www.drupal.org/project/drupal/issues/3294266
 */
class ComputedProjectReferenceFieldItemList extends EntityReferenceFieldItemList {

  use ComputedItemListTrait;

  /**
   * Computes the field value.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue(): void {

    // Fetch the user and determine respective field.
    $account = $this->getEntity();
    $field = $account->bundle() === 'user' ? 'field_participants' : 'uid';

    $project_storage = \Drupal::entityTypeManager()->getStorage('project');

    // Query projects referencing user.
    $query = $project_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition($field, $account->id());
    $project_ids = $query->execute();

    // This kind of programming is sinful.
    // @todo Work out query access for project entities.
    $projects = $project_storage->loadMultiple($project_ids);
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
