<?php

namespace Drupal\manager\Plugin\ManagerRule;

/**
 * Provides manager rule categories.
 */
enum RuleCategory {

  case Supress;
  case Deadline;
  case Other;

}
