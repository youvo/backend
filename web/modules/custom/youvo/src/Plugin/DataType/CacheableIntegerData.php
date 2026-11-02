<?php

namespace Drupal\youvo\Plugin\DataType;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;

/**
 * The integer data type with cacheability metadata.
 */
#[DataType(
  id: 'cacheable_integer',
  label: new TranslatableMarkup('Cacheable Integer'),
)]
class CacheableIntegerData extends IntegerData implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

}
