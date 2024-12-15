<?php

namespace Drupal\youvo\Plugin\DataType;

use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Core\TypedData\Plugin\DataType\StringData;

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

  /**
   * Delivers value that is cast to a string.
   *
   * Somehow the value can arrive as a StringItem here, when we use it as a
   * basefield. This fix should be considered a bandaid.
   *
   * @todo Find out why StringItem arrives here.
   *
   * {@inheritdoc}
   */
  public function getCastedValue(): string {
    if ($this->getValue() instanceof StringItem) {
      return $this->getValue()->getString();
    }
    return $this->getString();
  }

}
