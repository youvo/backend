<?php

namespace Drupal\lifecycle\Exception;

/**
 * An exception thrown when transitions fail.
 */
class LifecycleTransitionException extends \RuntimeException {

  /**
   * Constructs a new LifecycleTransitionException event.
   */
  public function __construct(
    protected string $transition,
    string $message = '',
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, $code, $previous);
  }

  /**
   * Gets the failing transition.
   */
  public function getTransition(): string {
    return $this->transition;
  }

}
