<?php

namespace Drupal\youvo;

use Drupal\field\Entity\FieldConfig;

class FieldValidator {

  public static function validate(FieldConfig $field, mixed $value) {
      $method = 'validate' . ucfirst($field->getType());
      return static::$method($field, $value);
  }

  public static function validateString(FieldConfig $field, mixed $value) {
    return is_string($value) &&
      strlen($value) <= $field->getItemDefinition()->getSetting('max_length');
  }

  public static function validateBool(FieldConfig $field, mixed $value) {
    return is_bool($value);
  }
}

