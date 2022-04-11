<?php

namespace Drupal\youvo\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class FieldAwareHttpException extends HttpException {

  private $field;

  public function __construct(
    int $statusCode,
    ?string $message = '',
    ?string $field = '',
    \Throwable $previous = null,
    array $headers = [],
    ?int $code = 0
  ) {
    parent::__construct($statusCode, $message, $previous, $headers, $code);
    $this->field = $field;
  }

  public function getField() {
    return $this->field;
  }
}
