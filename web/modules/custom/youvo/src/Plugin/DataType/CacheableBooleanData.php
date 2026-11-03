<?php

namespace Drupal\youvo\Plugin\DataType;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\BooleanData;

/**
 * The boolean data type with cacheability metadata.
 */
#[DataType(
  id: 'cacheable_boolean',
  label: new TranslatableMarkup('Cacheable Boolean'),
)]
class CacheableBooleanData extends BooleanData implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

}
