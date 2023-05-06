<?php

namespace Drupal\youvo\Plugin\DataType;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\TypedData\Plugin\DataType\BooleanData;

/**
 * The boolean data type with cacheability metadata.
 *
 * @DataType(
 *   id = "cacheable_boolean",
 *   label = @Translation("Cacheable Boolean")
 * )
 */
class CacheableBooleanData extends BooleanData implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

}
