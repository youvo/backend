<?php

namespace Drupal\academy\Plugin\DataType;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\TypedData\Plugin\DataType\IntegerData;

/**
 * The integer data type with cacheability metadata.
 *
 * @DataType(
 *   id = "cacheable_integer",
 *   label = @Translation("Cacheable Integer")
 * )
 */
class CacheableIntegerData extends IntegerData implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

}
