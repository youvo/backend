<?php

namespace Drupal\Core\TypedData\Plugin\DataType;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;

/**
 * The cacheable string data type.
 *
 * @DataType(
 *   id = "cacheable_string",
 *   label = @Translation("Cacheable string")
 * )
 */
class CacheableStringData extends StringData implements RefinableCacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

}
