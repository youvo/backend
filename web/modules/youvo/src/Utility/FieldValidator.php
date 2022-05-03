<?php

namespace Drupal\youvo\Utility;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Helper to validate fields in REST requests.
 */
class FieldValidator {

  /**
   * Determines the type of the field and delegates the validation.
   */
  public static function validate(FieldDefinitionInterface $field, mixed $value) {
    $type = explode("_", $field->getType());
    $method = 'validate' . implode(array_map('ucfirst', $type));
    if (method_exists(__CLASS__, $method)) {
      return static::$method($field, $value);
    }
    return FALSE;
  }

  /**
   * Validates field of type string.
   */
  public static function validateString(FieldDefinitionInterface $field, mixed $value) {
    $max_length = $field->getItemDefinition()->getSetting('max_length') ?: -1;
    return (is_null($value) ||is_numeric($value) || is_string($value)) &&
      ($max_length == -1 || strlen($value) <= $max_length);
  }

  /**
   * Validates field of type string long.
   */
  public static function validateStringLong(FieldDefinitionInterface $field, mixed $value) {
    return self::validateString($field, $value);
  }

  /**
   * Validates field of type text.
   */
  public static function validateText(FieldDefinitionInterface $field, mixed $value) {
    return is_null($value) || is_string($value);
  }

  /**
   * Validates field of type text with summary.
   */
  public static function validateTextWithSummary(FieldDefinitionInterface $field, mixed $value) {
    if (is_array($value)) {
      return array_key_exists('value', $value) &&
        (is_null($value['value']) || is_string($value['value'])) &&
        array_key_exists('summary', $value) &&
        (is_null($value['summary']) || is_string($value['summary']));
    }
    return is_null($value) ||is_string($value);
  }

  /**
   * Validates field of type boolean.
   */
  public static function validateBool(FieldDefinitionInterface $field, mixed $value) {
    return is_bool($value);
  }

  /**
   * Validates field of type email.
   */
  public static function validateEmail(FieldDefinitionInterface $field, mixed $value) {
    return \Drupal::service('email.validator')->isValid($value);
  }

}
