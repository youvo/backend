<?php

namespace Drupal\youvo\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Http exception that provides current field information.
 */
class FieldAwareHttpException extends HttpException {

  /**
   * The field of the exception.
   *
   * @var string|null
   */
  private $field;

  /**
   * Constructs a FieldAwareHttpException object.
   */
  public function __construct(
    int $statusCode,
    ?string $message = '',
    ?string $field = '',
    \Throwable $previous = NULL,
    array $headers = [],
    ?int $code = 0
  ) {
    parent::__construct($statusCode, $message, $previous, $headers, $code);
    $this->field = $field;
  }

  /**
   * Gets the field.
   */
  public function getField() {
    return $this->field;
  }

}
