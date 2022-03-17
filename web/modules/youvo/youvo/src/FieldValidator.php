<?php

namespace Drupal\youvo;

use Drupal\Core\Field\FieldDefinitionInterface;

class FieldValidator {

  public static function validate(FieldDefinitionInterface $field, mixed $value) {
    $type = strstr($field->getType(), '_', true) ?: $field->getType();
    $method = 'validate' . ucfirst($type);
    if (method_exists(__CLASS__, $method)) {
      return static::$method($field, $value);
    }
    return FALSE;
  }

  public static function validateString(FieldDefinitionInterface $field, mixed $value) {
    $max_length = $field->getItemDefinition()->getSetting('max_length') ?: -1;
    return is_string($value) &&
      ($max_length == - 1 || strlen($value) <= $max_length);
  }

  public static function validateText(FieldDefinitionInterface $field, mixed $value) {
    // @todo Validate different structures with summary etc.
    return is_string($value);
  }

  public static function validateBool(FieldDefinitionInterface $field, mixed $value) {
    return is_bool($value);
  }

  public static function validateEmail(FieldDefinitionInterface $field, mixed $value) {
    return \Drupal::service('email.validator')->isValid($value);
  }
}

