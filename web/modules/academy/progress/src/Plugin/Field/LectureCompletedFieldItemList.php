<?php

namespace Drupal\progress\Plugin\Field;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Utility\Error;
use Drupal\progress\LectureProgressManager;

/**
 * ComputedCompletedStatusFieldItemList class to generate a computed field.
 */
class LectureCompletedFieldItemList extends FieldItemList implements FieldItemListInterface {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function computeValue() {

    $completed = FALSE;

    try {
      $progress_manager = LectureProgressManager::create($this->getEntity());
      $progress = $progress_manager->getLectureProgress();
      $completed = (bool) $progress?->get('completed')->value;
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $variables = Error::decodeException($e);
      \Drupal::logger('progress')
        ->error('Can not retrieve lecture_progress enitity. %type: @message in %function (line %line of %file).', $variables);
    }
    catch (EntityMalformedException $e) {
      $variables = Error::decodeException($e);
      \Drupal::logger('progress')
        ->error('The progress of the requested lecture has inconsistent persistent data. %type: @message in %function (line %line of %file).', $variables);
    }
    finally {
      /** @var \Drupal\progress\Plugin\Field\FieldType\CacheableBooleanItem $item */
      $item = $this->createItem(0, $completed);
      $cacheability = (new CacheableMetadata())->setCacheMaxAge(0);
      $item->get('value')->addCacheableDependency($cacheability);
      $this->list[0] = $item;
    }
  }

}
